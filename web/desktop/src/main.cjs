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

let phpProcess = null;

function demarrerServeurPhp() {
  const apiDir = resolveApiDir();
  const phpBinary = resolvePhpBinary();
  const phpArgs = resolvePhpArgsCommuns();
  const { env } = envInstanceLocale();

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

app.whenReady().then(async () => {
  session.defaultSession.webRequest.onHeadersReceived((details, callback) => {
    callback({
      responseHeaders: {
        ...details.responseHeaders,
        "Content-Security-Policy": [
          "default-src 'self' 'unsafe-inline' data: http: https:",
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
  if (app.isPackaged && process.env.NODE_ENV === "production") {
    autoUpdater.checkForUpdatesAndNotify().catch(() => {});
  }
  app.on("activate", () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on("window-all-closed", () => {
  arreterServeurPhp();
  if (process.platform !== "darwin") app.quit();
});

app.on("before-quit", arreterServeurPhp);

exports.API_PORT = API_PORT;
