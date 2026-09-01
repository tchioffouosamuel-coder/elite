const { app, BrowserWindow, dialog, session } = require("electron");
const path = require("node:path");
const fs = require("node:fs");
const crypto = require("node:crypto");
const { spawn, execFileSync } = require("node:child_process");
const { autoUpdater } = require("electron-updater");

/**
 * Port fixe plutôt qu'un port libre choisi dynamiquement : le renderer (SPA
 * statique chargée en `file://`) doit connaître l'URL de l'API avant même
 * qu'Electron ait fini de démarrer le serveur PHP, sans aller-retour IPC
 * asynchrone au tout premier rendu. Un conflit avec un autre processus déjà
 * sur ce port est jugé improbable sur un poste desktop mono-utilisateur ;
 * à revoir si ça devient un problème réel en usage.
 */
const API_PORT = 8973;

/**
 * Emplacement du binaire PHP et du dossier de l'application Laravel.
 *
 * `resources/php` est un PHP portable généré par `scripts/bundle-php.cjs`
 * (jamais committé, cf. `.gitignore` — trop volumineux pour git, régénéré à
 * chaque build) : quand il est présent, même en développement, on l'utilise
 * de préférence pour tester exactement ce qui sera embarqué. `ELITES_PHP_BINARY`
 * permet de forcer un autre binaire (ex. déboguer avec le PHP système), et à
 * défaut de tout ça on retombe sur `php` du PATH.
 */
function resolveApiDir() {
  if (process.env.ELITES_API_DIR) return process.env.ELITES_API_DIR;

  return app.isPackaged
    ? path.join(process.resourcesPath, "api")
    : path.join(__dirname, "../../../api");
}

function resolvePhpBundleDir() {
  return app.isPackaged
    ? path.join(process.resourcesPath, "php")
    : path.join(__dirname, "../resources/php");
}

function resolvePhpBinary() {
  if (process.env.ELITES_PHP_BINARY) return process.env.ELITES_PHP_BINARY;

  const bundle = path.join(resolvePhpBundleDir(), "php.exe");
  return fs.existsSync(bundle) ? bundle : "php";
}

/** `-c`/`-d` explicites : ne jamais dépendre d'un php.ini système que le poste utilisateur peut ne pas avoir. */
function resolvePhpArgsCommuns() {
  const bundle = resolvePhpBundleDir();
  const ini = path.join(bundle, "php.ini");

  if (!fs.existsSync(ini)) return [];

  const args = [
    "-c", ini,
    "-d", `extension_dir=${path.join(bundle, "ext")}`,
    // Sans limite : c'est un serveur local de confiance, pas un hôte web
    // partagé. La limite par défaut (30s) coupait en plein milieu la toute
    // première synchronisation d'un compte accédant à plusieurs écoles
    // (chacune tirée intégralement l'une après l'autre dans la même requête
    // HTTP de provisioning) — observé en conditions réelles : deuxième école
    // interrompue à la moitié, troisième jamais atteinte.
    "-d", "max_execution_time=0",
    "-d", "max_input_time=-1",
  ];

  // Sans bundle de certificats explicite, `curl`/`openssl` sous Windows ne
  // valident aucune connexion HTTPS sortante (erreur cURL 60) — c'est le
  // seul appel HTTPS que fait ce PHP embarqué (sync:pull/sync:push vers le
  // serveur distant), donc son absence rend la synchronisation
  // silencieusement inopérante sans jamais faire échouer le démarrage.
  const cacert = path.join(bundle, "cacert.pem");
  if (fs.existsSync(cacert)) {
    args.push("-d", `curl.cainfo=${cacert}`, "-d", `openssl.cafile=${cacert}`);
  }

  return args;
}

/** Fichiers propres à cette installation : base SQLite locale et clé d'application, persistés hors du dossier `api/` (partagé, potentiellement réinstallé). */
function instancePaths() {
  const dir = app.getPath("userData");

  return {
    dir,
    database: path.join(dir, "elites-school.sqlite"),
    appKeyFile: path.join(dir, "app.key"),
  };
}

function lireOuCreerAppKey(appKeyFile) {
  if (fs.existsSync(appKeyFile)) {
    return fs.readFileSync(appKeyFile, "utf8").trim();
  }

  // Même format que `php artisan key:generate` (AES-256-CBC, 32 octets encodés base64).
  const cle = `base64:${crypto.randomBytes(32).toString("base64")}`;
  fs.writeFileSync(appKeyFile, cle, "utf8");

  return cle;
}

function envInstanceLocale() {
  const { database, appKeyFile } = instancePaths();

  if (!fs.existsSync(database)) fs.writeFileSync(database, "");

  return {
    env: {
      ...process.env,
      APP_ENV: "production",
      APP_DEBUG: "false",
      // `.env` fixe une valeur de dev (`http://127.0.0.1:8000`) qui ne
      // correspond à rien ici : sans cette surcharge, `asset()` (photos,
      // logos d'établissement) génère des URLs vers un port mort plutôt que
      // le vrai port du serveur PHP embarqué (`API_PORT`).
      APP_URL: `http://127.0.0.1:${API_PORT}`,
      APP_KEY: lireOuCreerAppKey(appKeyFile),
      DB_CONNECTION: "sqlite",
      DB_DATABASE: database,
      CACHE_STORE: "file",
      SESSION_DRIVER: "file",
      QUEUE_CONNECTION: "sync",
      MAIL_MAILER: "log",
      // Active l'outbox locale (cf. EnregistrerDansOutboxLocale côté API) :
      // sans effet sur le serveur distant, qui ne positionne jamais cette
      // variable.
      SYNC_LOCAL_REPLICA: "true",
    },
  };
}

/**
 * `public/storage` doit pointer vers `storage/app/public` (photos élèves,
 * logos d'établissement…) — c'est de là que le serveur PHP embarqué
 * (`-t public`) sert tout ce qu'`asset('storage/...')` génère côté API.
 *
 * `php artisan storage:link` crée normalement ce lien, mais un vrai lien
 * symbolique exige des droits admin sous Windows ; et à l'installation,
 * l'outil de packaging qui copie `api/` déréférence le symlink du dépôt
 * source en un dossier réel figé au contenu du moment — les uploads
 * suivants atterrissent dans `storage/app/public` sans jamais y apparaître.
 * Une jonction de répertoire (`fs.symlinkSync(..., "junction")`), elle, ne
 * demande aucune élévation sous Windows : recréée à chaque démarrage, elle
 * garantit que `public/storage` reflète toujours `storage/app/public`
 * plutôt qu'un instantané pris au packaging.
 */
function assurerLienStorage(apiDir) {
  const cible = path.join(apiDir, "storage", "app", "public");
  const lien = path.join(apiDir, "public", "storage");

  fs.mkdirSync(cible, { recursive: true });

  if (fs.existsSync(lien)) {
    if (fs.lstatSync(lien).isSymbolicLink()) return;

    // Dossier réel laissé par le packaging (symlink déréférencé) : à
    // remplacer par la jonction, pas à fusionner — son contenu est un
    // instantané périmé, déjà dupliqué dans `storage/app/public` d'origine.
    fs.rmSync(lien, { recursive: true, force: true });
  }

  fs.symlinkSync(cible, lien, "junction");
}

let phpProcess = null;

function demarrerServeurPhp() {
  const apiDir = resolveApiDir();
  const phpBinary = resolvePhpBinary();
  const phpArgs = resolvePhpArgsCommuns();
  const { env } = envInstanceLocale();

  try {
    assurerLienStorage(apiDir);
  } catch (erreur) {
    // Non bloquant : mieux vaut démarrer avec des images cassées qu'un
    // écran d'erreur au tout premier lancement pour un souci de stockage.
    console.error(`[storage] jonction public/storage impossible : ${erreur.message}`);
  }

  // Toujours migrer, jamais seulement « si le fichier vient d'être créé » :
  // `artisan migrate` est idempotent (rien à faire si tout est déjà en
  // place) et détecter un « premier lancement » par la seule existence du
  // fichier est fragile — un fichier sqlite d'une version antérieure de
  // l'app (autre schéma, ou resté d'une install précédente) existerait déjà
  // sans être migré pour autant, et la vérification passerait à côté.
  // Synchrone à dessein — la fenêtre n'a rien d'utile à montrer avant que
  // le schéma soit à jour.
  execFileSync(phpBinary, [...phpArgs, "artisan", "migrate", "--force"], { cwd: apiDir, env });

  phpProcess = spawn(
    phpBinary,
    [...phpArgs, "-S", `127.0.0.1:${API_PORT}`, "-t", "public"],
    { cwd: apiDir, env, stdio: "pipe" },
  );

  phpProcess.stderr.on("data", (chunk) => {
    // Le serveur de développement PHP écrit son journal d'accès sur stderr :
    // utile pour diagnostiquer un poste utilisateur, jamais fatal en soi.
    console.error(`[php] ${chunk}`);
  });

  phpProcess.on("exit", (code) => {
    if (code !== null && code !== 0) console.error(`[php] serveur arrêté (code ${code})`);
  });
}

const INTERVALLE_SYNC_MS = 5 * 60 * 1000;

let intervalleSyncId = null;
let syncEnCours = false;

/** Une commande artisan, résolue une fois le processus terminé (jamais rejetée : un échec de sync ne doit pas remonter plus haut que son propre log). */
function executerArtisan(commande) {
  const apiDir = resolveApiDir();
  const phpBinary = resolvePhpBinary();
  const phpArgs = resolvePhpArgsCommuns();
  const { env } = envInstanceLocale();

  return new Promise((resolve) => {
    const proc = spawn(phpBinary, [...phpArgs, "artisan", commande], { cwd: apiDir, env, stdio: "pipe" });

    proc.stderr.on("data", (chunk) => console.error(`[${commande}] ${chunk}`));
    proc.on("exit", (code) => {
      if (code !== 0) console.error(`[${commande}] terminé avec le code ${code}`);
      resolve();
    });
    proc.on("error", (erreur) => {
      console.error(`[${commande}] impossible de démarrer : ${erreur.message}`);
      resolve();
    });
  });
}

/**
 * Seul déclencheur de synchronisation après le provisioning initial (qui ne
 * tire qu'une fois, au moment de la connexion — cf.
 * `DesktopProvisioningController::provisionner()`) : sans cette boucle,
 * rien ne pousse jamais les écritures faites hors-ligne vers le serveur
 * distant, ni ne tire ses propres mises à jour — le frontend n'appelle
 * nulle part `/desktop/synchroniser`, il n'existe ni bouton « Synchroniser »
 * ni tâche planifiée côté serveur (le scheduler Laravel exigerait de toute
 * façon un cron que ce poste desktop ne fait pas tourner).
 *
 * Inconditionnel dès le démarrage plutôt que conditionné à un provisioning
 * déjà en place : `sync:pull`/`sync:push` sont des no-op silencieux
 * (`DesktopProvisioning::actuelle() === null`) tant qu'aucun poste n'est
 * lié à un compte, donc sans risque à lancer avant que l'utilisateur se
 * soit connecté.
 */
async function lancerSyncPeriodique() {
  const executer = async () => {
    if (syncEnCours) return;
    syncEnCours = true;

    try {
      // Toujours dans cet ordre : un push après un pull rejoue sur une base
      // déjà à jour, l'inverse risquerait de pousser une écriture locale
      // qu'un pull imminent aurait de toute façon dû arbitrer en premier
      // (le plus récent gagne, cf. `SyncPull::appliquerLigne()`).
      await executerArtisan("sync:pull");
      await executerArtisan("sync:push");
    } finally {
      syncEnCours = false;
    }
  };

  await executer();
  intervalleSyncId = setInterval(executer, INTERVALLE_SYNC_MS);
}

function arreterSyncPeriodique() {
  if (intervalleSyncId) clearInterval(intervalleSyncId);
  intervalleSyncId = null;
}

/**
 * Attend que le serveur PHP réponde, avant de charger le renderer dessus.
 *
 * Délai généreux (2 minutes) plutôt qu'un simple démarrage rapide : au tout
 * premier lancement sur un poste, l'antivirus scanne `php.exe` — binaire
 * inconnu, jamais vu — avant de l'autoriser à s'exécuter, ce qui peut
 * prendre plus d'une minute. Une fois ce binaire « connu » de l'antivirus,
 * les lancements suivants démarrent en quelques secondes ; observé
 * directement lors des tests (15s de délai insuffisant au premier
 * lancement, 5s au second).
 */
async function attendreServeurPret(tentativesMax = 240) {
  for (let tentative = 0; tentative < tentativesMax; tentative++) {
    try {
      const reponse = await fetch(`http://127.0.0.1:${API_PORT}/up`);
      if (reponse.ok) return;
    } catch {
      // Pas encore prêt : nouvelle tentative après une courte pause.
    }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }

  throw new Error("Le serveur local n'a pas démarré à temps.");
}

function arreterServeurPhp() {
  phpProcess?.kill();
  phpProcess = null;
}

function createWindow() {
  const window = new BrowserWindow({
    width: 1440,
    height: 900,
    minWidth: 1100,
    minHeight: 700,
    webPreferences: {
      preload: path.join(__dirname, "preload.cjs"),
      contextIsolation: true,
      nodeIntegration: false,
      // Sans ceci, le lecteur PDF intégré de Chromium reste désactivé et
      // tout <iframe src="blob:..."> pointant vers un PDF (l'aperçu de
      // document) s'affiche vide, sans aucune erreur dans les DevTools.
      plugins: true,
    },
  });

  // Sans ceci, Electron REFUSE silencieusement tout `window.open()` par
  // défaut (aucune erreur JS, aucun log) — exactement le pattern utilisé
  // pour prévisualiser chaque PDF généré ailleurs dans l'app (fetch
  // authentifié → blob → `window.open(blobUrl, '_blank')`, l'appel direct
  // à l'URL de l'API étant impossible sans pouvoir y joindre l'en-tête
  // d'autorisation). `allow` ouvre une vraie fenêtre Electron sur ce blob
  // ou cette URL, exactement comme le ferait un nouvel onglet de navigateur.
  window.webContents.setWindowOpenHandler(() => ({ action: "allow" }));

  const dist = app.isPackaged
    ? path.join(process.resourcesPath, "web-dist")
    : path.join(__dirname, "../../dist");
  window.loadFile(path.join(dist, "index.html"));
}

/**
 * Vérifie les mises à jour publiées sur les releases GitHub du dépôt
 * (config `build.publish` de package.json, lue depuis `app-update.yml`
 * embarqué au build — aucune configuration ici). Ignoré hors installation
 * packagée : en dev, il n'y a ni `app-update.yml` ni installeur NSIS à
 * remplacer, `checkForUpdates` échouerait pour rien à chaque lancement.
 */
function configurerAutoUpdate() {
  if (!app.isPackaged) return;

  autoUpdater.autoDownload = true;
  autoUpdater.autoInstallOnAppQuit = true;

  autoUpdater.on("error", (erreur) => {
    console.error("[update] échec de la vérification/du téléchargement", erreur);
  });

  // Téléchargée en tâche de fond, l'installation ne se fait qu'après accord
  // explicite : forcer un redémarrage sans prévenir couperait l'utilisateur
  // en pleine saisie (bulletins, absences...) sans sauvegarde préalable côté
  // SPA.
  autoUpdater.on("update-downloaded", (info) => {
    dialog.showMessageBox({
      type: "info",
      title: "Mise à jour disponible",
      message: `Une nouvelle version d'Elites School (${info.version}) a été téléchargée.`,
      detail: "Elle sera installée au prochain redémarrage de l'application.",
      buttons: ["Redémarrer maintenant", "Plus tard"],
      defaultId: 0,
      cancelId: 1,
    }).then(({ response }) => {
      if (response === 0) autoUpdater.quitAndInstall();
    });
  });

  const verifier = () => autoUpdater.checkForUpdates().catch((erreur) => {
    console.error("[update] vérification impossible", erreur);
  });

  verifier();
  // Poste desktop d'école : l'appli reste souvent ouverte toute la journée
  // sans jamais redémarrer, donc une seule vérification au lancement ne
  // suffit pas à faire arriver une mise à jour publiée en cours de journée.
  setInterval(verifier, 4 * 60 * 60 * 1000);
}

app.whenReady().then(async () => {
  session.defaultSession.webRequest.onHeadersReceived((details, callback) => {
    callback({
      responseHeaders: {
        ...details.responseHeaders,
        // `frame-src` distinct de `default-src` : l'aperçu de document (PDF
        // généré, converti en blob puis affiché dans un <iframe>) ne
        // s'affichait pas sans ce `blob:` explicite — `default-src` ne le
        // couvre pas pour le framing, seulement pour les autres types de
        // ressources.
        "Content-Security-Policy": [
          "default-src 'self' 'unsafe-inline' data: blob: http: https:; frame-src 'self' blob:",
        ],
      },
    });
  });

  try {
    demarrerServeurPhp();
    await attendreServeurPret();
  } catch (erreur) {
    console.error(erreur);
    // Sans ce message, l'utilisateur ne voit qu'un écran de connexion cassé
    // (erreurs réseau silencieuses dans les DevTools, jamais ouvertes en
    // usage normal) sans aucun indice sur ce qui a échoué.
    dialog.showErrorBox(
      "Elites School — démarrage impossible",
      "Le serveur local n'a pas pu démarrer.\n\n"
        + "Cause fréquente : un antivirus qui analyse encore les fichiers de l'application "
        + "lors de sa toute première exécution. Fermez cette fenêtre et relancez Elites School — "
        + "les lancements suivants sont nettement plus rapides.\n\n"
        + `Détail technique : ${erreur.message}`,
    );
  }

  createWindow();
  configurerAutoUpdate();
  // Ni attendu ni dans le bloc try/catch ci-dessus : un aléa réseau au tout
  // premier cycle ne doit pas empêcher la fenêtre de s'ouvrir, et chaque
  // commande gère déjà elle-même son propre échec (voir `executerArtisan`).
  lancerSyncPeriodique();
  app.on("activate", () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on("window-all-closed", () => {
  arreterSyncPeriodique();
  arreterServeurPhp();
  if (process.platform !== "darwin") app.quit();
});

app.on("before-quit", () => {
  arreterSyncPeriodique();
  arreterServeurPhp();
});

exports.API_PORT = API_PORT;
