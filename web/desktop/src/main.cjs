const {
  app,
  BrowserWindow,
  Menu,
  dialog,
  session,
  ipcMain,
} = require("electron");
const path = require("node:path");
const fs = require("node:fs");
const os = require("node:os");
const http = require("node:http");
const crypto = require("node:crypto");
const { spawn, execFile } = require("node:child_process");
const { autoUpdater } = require("electron-updater");

/**
 * Port fixe plutôt qu'un port libre choisi dynamiquement : le renderer (SPA
 * statique chargée en `file://`) doit connaître l'URL de l'API avant même
 * qu'Electron ait fini de démarrer le serveur PHP, sans aller-retour IPC
 * asynchrone au tout premier rendu. Un conflit avec un autre processus déjà
 * sur ce port est jugé improbable sur un poste desktop mono-utilisateur ;
 * à revoir si ça devient un problème réel en usage.
 *
 * Ce port est celui du répartiteur (cf. `demarrerRepartiteur()`) ; les
 * serveurs PHP eux-mêmes écoutent sur les ports qui le suivent.
 */
const API_PORT = 8973;

/**
 * Le serveur intégré de PHP (`php -S`) ne traite qu'une requête à la fois, et
 * `PHP_CLI_SERVER_WORKERS` n'existe pas sous Windows : un écran qui lance huit
 * appels les voyait servis l'un après l'autre. On démarre donc plusieurs
 * serveurs, derrière un répartiteur qui adresse chaque requête au moins
 * occupé. Plafonné : chaque serveur coûte de la mémoire, et SQLite n'admet de
 * toute façon qu'un écrivain à la fois.
 */
const NOMBRE_WORKERS = Math.max(2, Math.min(4, os.cpus().length - 1));

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

/**
 * `-c`/`-d` explicites : ne jamais dépendre d'un php.ini système que le poste
 * utilisateur peut ne pas avoir.
 *
 * Ne s'applique QUE si `resolvePhpBinary()` a effectivement choisi le PHP du
 * bundle : un PHP système visé par `ELITES_PHP_BINARY` (ou par repli sur le
 * `PATH` quand l'antivirus a supprimé `php.exe` du bundle sans forcément
 * avoir touché au reste du dossier) a son propre `php.ini` et ses propres
 * extensions, compilées pour SA version — lui imposer l'`extension_dir` du
 * bundle (compilé pour une autre version/ABI) ferait échouer le chargement
 * de toutes les extensions au lieu de servir de filet de secours.
 */
function resolvePhpArgsCommuns() {
  if (process.env.ELITES_PHP_BINARY) return [];

  const bundle = resolvePhpBundleDir();
  const bundleBinaire = path.join(bundle, "php.exe");

  if (!fs.existsSync(bundleBinaire)) return [];

  const ini = path.join(bundle, "php.ini");

  if (!fs.existsSync(ini)) return [];

  const args = [
    "-c",
    ini,
    "-d",
    // Une valeur `-d` est analysée par PHP avec la même grammaire qu'une
    // ligne de php.ini : non protégée par des guillemets, elle passe par
    // l'évaluateur de constantes de l'ini (`~`, `!`, `|`, `&`, parenthèses
    // significatifs, utilisés pour des réglages comme `error_reporting =
    // E_ALL & ~E_NOTICE`). Sur un poste installé dans « Program Files
    // (x86) » (choix « pour tous les utilisateurs » de l'installeur), la
    // parenthèse de « (x86) » finissait droit dans cette grammaire et
    // faisait échouer PHP dès le démarrage avec « syntax error, unexpected
    // '(' » — observé en conditions réelles, toutes les valeurs de chemin
    // ci-dessous en étaient au même risque. Entre guillemets, la valeur est
    // prise telle quelle, sans interprétation.
    `extension_dir="${path.join(bundle, "ext")}"`,
    // Sans limite : c'est un serveur local de confiance, pas un hôte web
    // partagé. La limite par défaut (30s) coupait en plein milieu la toute
    // première synchronisation d'un compte accédant à plusieurs écoles
    // (chacune tirée intégralement l'une après l'autre dans la même requête
    // HTTP de provisioning) — observé en conditions réelles : deuxième école
    // interrompue à la moitié, troisième jamais atteinte.
    "-d",
    "max_execution_time=0",
    "-d",
    "max_input_time=-1",
    // `php.ini-production` s'arrête à 128 Mo : trop juste pour une page de
    // synchronisation de plusieurs milliers de lignes (cf. `SyncPull`) ou un
    // PDF de bulletins d'une classe entière.
    "-d",
    "memory_limit=1024M",
  ];

  // Sans bundle de certificats explicite, `curl`/`openssl` sous Windows ne
  // valident aucune connexion HTTPS sortante (erreur cURL 60) — c'est le
  // seul appel HTTPS que fait ce PHP embarqué (sync:pull/sync:push vers le
  // serveur distant), donc son absence rend la synchronisation
  // silencieusement inopérante sans jamais faire échouer le démarrage.
  const cacert = path.join(bundle, "cacert.pem");
  if (fs.existsSync(cacert)) {
    args.push(
      "-d",
      `curl.cainfo="${cacert}"`,
      "-d",
      `openssl.cafile="${cacert}"`,
    );
  }

  return args;
}

/**
 * Arguments des seuls serveurs PHP (`-S`), en plus des communs : OPcache.
 *
 * Sans lui, chaque requête recompile tout Laravel — mesuré à ~290 ms de coût
 * fixe par appel, contre ~50 ms avec — et le navigateur en envoie deux par
 * lecture (pré-vérification CORS comprise). Les serveurs partagent le même
 * cache en mémoire : seul le premier paie la compilation.
 *
 * Pas pour les commandes artisan : un processus qui ne vit qu'une seconde
 * n'a pas le temps de rentabiliser le cache (mesuré plus lent avec).
 */
function resolvePhpArgsServeur() {
  const args = resolvePhpArgsCommuns();
  const extOpcache = path.join(resolvePhpBundleDir(), "ext", "php_opcache.dll");

  if (args.length === 0 || !fs.existsSync(extOpcache)) return args;

  return [
    ...args,
    "-d",
    "zend_extension=opcache",
    "-d",
    "opcache.enable=1",
    "-d",
    "opcache.enable_cli=1",
    // Propre à cette version : après une mise à jour, les serveurs ne
    // doivent pas se rattacher au cache d'une instance encore ouverte de
    // l'ancienne, ni à celui d'un autre PHP installé sur le poste.
    "-d",
    `opcache.cache_id=elites-school-${app.getVersion()}`,
    "-d",
    "opcache.memory_consumption=128",
    "-d",
    "opcache.interned_strings_buffer=16",
    "-d",
    "opcache.max_accelerated_files=20000",
    // Les fichiers de l'application ne changent qu'à une mise à jour, qui
    // redémarre de toute façon les serveurs : inutile de revérifier leurs
    // dates à chaque requête.
    "-d",
    "opcache.revalidate_freq=60",
    "-d",
    "realpath_cache_size=4096K",
    "-d",
    "realpath_cache_ttl=600",
  ];
}

/** Fichiers propres à cette installation : base SQLite locale et clé d'application, persistés hors du dossier `api/` (partagé, potentiellement réinstallé). */
function instancePaths() {
  const dir = app.getPath("userData");

  return {
    dir,
    database: path.join(dir, "elites-school.sqlite"),
    appKeyFile: path.join(dir, "app.key"),
    schemaFile: path.join(dir, "schema.version"),
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

/**
 * `execFileSync` échoue avec un message générique (« Command failed: ... »,
 * sans plus) dès que le process a produit un stdout/stderr vide au moment du
 * crash — c'est-à-dire précisément le cas où php.exe plante avant même
 * d'écrire quoi que ce soit (mauvaise architecture, DLL manquante, binaire
 * incompatible avec un `extension_dir` qui n'est pas le sien...). Sans ce
 * détour, la boîte d'erreur affichée à l'utilisateur ne contient jamais la
 * vraie cause — observé en conditions réelles : un poste où
 * `ELITES_PHP_BINARY` pointait vers un PHP système n'affichait que
 * « Command failed: C:\php\php.exe artisan migrate --force », sans le
 * moindre indice sur pourquoi.
 */
function detailErreurCommande(erreur) {
  const morceaux = [erreur.message];

  const stdout = erreur.stdout?.toString("utf8").trim();
  const stderr = erreur.stderr?.toString("utf8").trim();

  if (stderr) morceaux.push(`stderr : ${stderr}`);
  if (stdout) morceaux.push(`stdout : ${stdout}`);
  if (typeof erreur.status === "number")
    morceaux.push(`code de sortie : ${erreur.status}`);
  if (!stdout && !stderr)
    morceaux.push(
      "(aucune sortie du programme — il a probablement échoué à démarrer)",
    );

  return morceaux.join("\n");
}

/**
 * `execFile` en promesse, rejetée avec stdout/stderr attachés à l'erreur
 * (cf. `detailErreurCommande()`). Asynchrone à dessein : pendant ces
 * commandes de préparation, la fenêtre d'attente doit rester vivante —
 * `execFileSync` figeait tout le processus principal, que Windows finissait
 * par signaler comme « ne répond pas ».
 */
function executerCommande(binaire, args, options = {}) {
  return new Promise((resolve, reject) => {
    execFile(
      binaire,
      args,
      { maxBuffer: 16 * 1024 * 1024, windowsHide: true, ...options },
      (erreur, stdout, stderr) => {
        if (!erreur) {
          resolve(stdout);
          return;
        }
        erreur.stdout = stdout;
        erreur.stderr = stderr;
        if (typeof erreur.code === "number") erreur.status = erreur.code;
        reject(erreur);
      },
    );
  });
}

/** Un fichier du cœur de Laravel, au fond de l'arborescence : sa présence atteste d'une extraction menée à terme, pas seulement entamée. */
function vendorComplet(vendorDir) {
  return (
    fs.existsSync(path.join(vendorDir, "autoload.php")) &&
    fs.existsSync(
      path.join(
        vendorDir,
        "laravel",
        "framework",
        "src",
        "Illuminate",
        "Foundation",
        "Application.php",
      ),
    )
  );
}

/**
 * Le `vendor` Composer de l'API embarquée (plusieurs milliers de fichiers)
 * est livré compressé en une seule archive (`vendor.zip`, cf.
 * `scripts/build-installer.cjs`) plutôt qu'en clair — un poste a vu ce
 * dossier disparaître intégralement après une installation par ailleurs
 * réussie, sans la moindre trace côté Windows Defender ; livrer UN fichier
 * plutôt que des milliers déplace au moins le risque du processus
 * d'installation (NSIS) vers l'application elle-même. Décompressé une seule
 * fois — les lancements suivants trouvent le vendor déjà en place et ne
 * font rien.
 *
 * `tar` (livré avec Windows depuis la version 1803) d'abord : il extrait la
 * même archive plusieurs fois plus vite qu'`Expand-Archive`, qui reste le
 * repli s'il est absent ou s'il laisse un vendor incomplet.
 */
async function assurerVendorExtrait(apiDir) {
  const vendorDir = path.join(apiDir, "vendor");
  const vendorZip = path.join(apiDir, "vendor.zip");

  if (fs.existsSync(path.join(vendorDir, "autoload.php"))) return;
  // Absent en développement (le vendor y est déjà en clair, cf.
  // `resolveApiDir()` qui pointe alors sur le dépôt source) : rien à faire.
  if (!fs.existsSync(vendorZip)) return;

  signalerEtapeDemarrage(
    "Première utilisation : préparation de l'application…",
  );

  try {
    fs.mkdirSync(vendorDir, { recursive: true });
    await executerCommande("tar", ["-xf", vendorZip, "-C", vendorDir]);
  } catch (erreur) {
    console.error(`[vendor] extraction par tar impossible : ${erreur.message}`);
  }

  if (vendorComplet(vendorDir)) return;

  fs.rmSync(vendorDir, { recursive: true, force: true });
  await executerCommande("powershell", [
    "-NoProfile",
    "-Command",
    `Expand-Archive -Path '${vendorZip}' -DestinationPath '${vendorDir}' -Force`,
  ]);
}

/**
 * Empreinte de ce que `artisan migrate` aurait à appliquer : la version de
 * l'application installée et la liste de ses fichiers de migration — un
 * installeur reconstruit sans changer de version, mais avec une migration de
 * plus, doit quand même migrer. `null` hors installation packagée (ou si la
 * liste est illisible) : en développement le contenu d'une migration change
 * sans que son nom bouge, on migre alors à chaque lancement.
 */
function empreinteSchema() {
  if (!app.isPackaged) return null;

  try {
    const migrations = fs
      .readdirSync(path.join(resolveApiDir(), "database", "migrations"))
      .sort()
      .join("\n");

    return crypto
      .createHash("sha1")
      .update(`${app.getVersion()}\n${migrations}`)
      .digest("hex");
  } catch {
    return null;
  }
}

/**
 * Le schéma local est-il déjà celui de cette version ? Vrai seulement si la
 * dernière migration RÉUSSIE (cf. `migrerSiNecessaire()`) portait la même
 * empreinte, et que la base n'a pas été vidée entre-temps (scripts de
 * réinitialisation, fichier supprimé à la main — recréé vide par
 * `envInstanceLocale()`).
 */
function schemaAJour() {
  const { database, schemaFile } = instancePaths();
  const empreinte = empreinteSchema();

  if (empreinte === null) return false;

  try {
    return (
      fs.statSync(database).size > 0 &&
      fs.readFileSync(schemaFile, "utf8").trim() === empreinte
    );
  } catch {
    return false;
  }
}

/**
 * `artisan migrate` démarre tout Laravel pour, presque toujours, constater
 * qu'il n'y a rien à faire : une à plusieurs secondes de fenêtre vide à
 * chaque lancement sur un poste modeste. On ne le rejoue donc que si
 * l'empreinte a changé depuis la dernière migration réussie — pas « si
 * le fichier vient d'être créé », critère trop fragile : un fichier sqlite
 * d'une version antérieure existerait déjà sans être migré pour autant.
 */
async function migrerSiNecessaire(apiDir, phpBinary, env) {
  if (schemaAJour()) return;

  signalerEtapeDemarrage("Mise à jour de la base locale…");

  await executerCommande(
    phpBinary,
    [...resolvePhpArgsCommuns(), "artisan", "migrate", "--force"],
    { cwd: apiDir, env },
  );

  const empreinte = empreinteSchema();
  if (empreinte !== null) {
    fs.writeFileSync(instancePaths().schemaFile, empreinte, "utf8");
  }
}

/** Serveurs PHP en service, un par port (cf. `NOMBRE_WORKERS`). */
const workers = [];
let repartiteur = null;
let arretDemande = false;

const REDEMARRAGES_MAX = 5;

function demarrerWorker(worker) {
  const apiDir = resolveApiDir();
  const { env } = envInstanceLocale();

  worker.pret = false;
  worker.actives = 0;
  worker.proc = spawn(
    resolvePhpBinary(),
    [
      ...resolvePhpArgsServeur(),
      "-S",
      `127.0.0.1:${worker.port}`,
      "-t",
      "public",
    ],
    { cwd: apiDir, env, stdio: "pipe", windowsHide: true },
  );

  // Sans lecteur, le tube de stdout finit par se remplir et bloquer PHP.
  worker.proc.stdout.resume();
  worker.proc.stderr.on("data", (chunk) => {
    // Le serveur de développement PHP écrit son journal d'accès sur stderr :
    // utile pour diagnostiquer un poste utilisateur, jamais fatal en soi.
    console.error(`[php:${worker.port}] ${chunk}`);
  });

  worker.proc.on("error", (erreur) => {
    console.error(`[php:${worker.port}] impossible de démarrer : ${erreur.message}`);
  });

  worker.proc.on("exit", (code) => {
    worker.pret = false;
    if (arretDemande) return;

    console.error(`[php:${worker.port}] serveur arrêté (code ${code})`);

    // Un serveur tombé en cours de route (antivirus, plantage d'une
    // extension) est relancé : les autres continuent de répondre pendant ce
    // temps. Plafonné pour ne pas tourner en boucle sur une cause durable.
    if (worker.redemarrages >= REDEMARRAGES_MAX) return;
    worker.redemarrages++;
    setTimeout(() => {
      if (arretDemande) return;
      demarrerWorker(worker);
      attendreWorkerPret(worker).catch((erreur) => console.error(erreur));
    }, 1000);
  });
}

/**
 * Attend qu'un serveur PHP réponde, avant de lui adresser la moindre requête.
 *
 * Délai généreux (2 minutes) plutôt qu'un simple démarrage rapide : au tout
 * premier lancement sur un poste, l'antivirus scanne `php.exe` — binaire
 * inconnu, jamais vu — avant de l'autoriser à s'exécuter, ce qui peut
 * prendre plus d'une minute. Une fois ce binaire « connu » de l'antivirus,
 * les lancements suivants démarrent en quelques secondes ; observé
 * directement lors des tests (15s de délai insuffisant au premier
 * lancement, 5s au second).
 */
async function attendreWorkerPret(worker, tentativesMax = 480) {
  const proc = worker.proc;

  for (let tentative = 0; tentative < tentativesMax; tentative++) {
    // Relancé ou arrêté entre-temps : cette attente ne concerne plus personne.
    if (worker.proc !== proc || arretDemande) return;

    try {
      const reponse = await fetch(`http://127.0.0.1:${worker.port}/up`);
      if (reponse.ok) {
        worker.pret = true;
        return;
      }
    } catch {
      // Pas encore prêt : nouvelle tentative après une courte pause.
    }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }

  throw new Error("Le serveur local n'a pas démarré à temps.");
}

/** Le serveur prêt qui a le moins de requêtes en cours. */
function choisirWorker() {
  let choisi = null;

  for (const worker of workers) {
    if (!worker.pret) continue;
    if (choisi === null || worker.actives < choisi.actives) choisi = worker;
  }

  return choisi;
}

/**
 * Pré-vérification CORS (`OPTIONS`), répondue ici sans jamais atteindre PHP.
 *
 * Le renderer est chargé en `file://` et appelle `http://127.0.0.1` avec un
 * en-tête `Authorization` : pour le navigateur, chaque appel est une requête
 * inter-origines « non simple », précédée d'une pré-vérification. Laravel y
 * répondait en démarrant tout le framework — autant de temps que la vraie
 * requête, pour ne rien dire que ces quatre en-têtes. Même politique que
 * `config/cors.php` (tout autoriser : ce serveur n'écoute que sur la boucle
 * locale).
 */
function repondrePreverification(req, res) {
  res.writeHead(204, {
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Methods":
      req.headers["access-control-request-method"] ?? "*",
    "Access-Control-Allow-Headers":
      req.headers["access-control-request-headers"] ?? "*",
    "Access-Control-Max-Age": "7200",
    Vary: "Access-Control-Request-Method, Access-Control-Request-Headers",
  });
  res.end();
}

function relayer(req, res) {
  if (req.method === "OPTIONS" && req.headers["access-control-request-method"]) {
    repondrePreverification(req, res);
    return;
  }

  const worker = choisirWorker();

  if (worker === null) {
    res.writeHead(503, {
      "Content-Type": "application/json",
      "Access-Control-Allow-Origin": "*",
    });
    res.end(
      JSON.stringify({ message: "Le service local redémarre. Réessayez." }),
    );
    return;
  }

  worker.actives++;
  let libere = false;
  const liberer = () => {
    if (libere) return;
    libere = true;
    worker.actives--;
  };

  const amont = http.request(
    {
      host: "127.0.0.1",
      port: worker.port,
      method: req.method,
      path: req.url,
      // `Host` conservé tel quel : Laravel génère ses URLs (photos, logos)
      // d'après lui, elles doivent pointer sur le répartiteur et non sur le
      // port d'un serveur en particulier.
      headers: { ...req.headers, connection: "close" },
      agent: false,
    },
    (reponse) => {
      const enTetes = { ...reponse.headers };
      // Propres à la liaison répartiteur → PHP, pas à celle du renderer.
      delete enTetes.connection;
      delete enTetes["keep-alive"];

      res.writeHead(reponse.statusCode ?? 502, enTetes);
      reponse.pipe(res);
      reponse.on("end", liberer);
      reponse.on("close", liberer);
    },
  );

  amont.on("error", (erreur) => {
    liberer();
    console.error(`[repartiteur] ${req.method} ${req.url} : ${erreur.message}`);

    if (res.headersSent) {
      res.destroy();
      return;
    }
    res.writeHead(502, {
      "Content-Type": "application/json",
      "Access-Control-Allow-Origin": "*",
    });
    res.end(
      JSON.stringify({ message: "Le service local n'a pas répondu. Réessayez." }),
    );
  });

  // Requête abandonnée par le renderer (changement d'écran, annulation) :
  // inutile de laisser PHP la mener à terme pour personne.
  res.on("close", () => {
    if (!res.writableEnded) amont.destroy();
  });

  req.pipe(amont);
}

function demarrerRepartiteur() {
  return new Promise((resolve, reject) => {
    repartiteur = http.createServer(relayer);
    // Aucune limite propre : un export volumineux ou un PDF de bulletins
    // peut légitimement prendre plusieurs minutes.
    repartiteur.requestTimeout = 0;
    repartiteur.headersTimeout = 0;
    repartiteur.once("error", reject);
    repartiteur.listen(API_PORT, "127.0.0.1", resolve);
  });
}

/**
 * Prépare l'API locale (vendor, stockage, schéma) puis démarre les serveurs
 * PHP et le répartiteur. Résolue dès qu'UN serveur répond : les autres
 * rejoignent la rotation au fur et à mesure (cf. `choisirWorker()`).
 */
async function demarrerServeurPhp() {
  const apiDir = resolveApiDir();
  const { env } = envInstanceLocale();

  await assurerVendorExtrait(apiDir);

  try {
    assurerLienStorage(apiDir);
  } catch (erreur) {
    // Non bloquant : mieux vaut démarrer avec des images cassées qu'un
    // écran d'erreur au tout premier lancement pour un souci de stockage.
    console.error(
      `[storage] jonction public/storage impossible : ${erreur.message}`,
    );
  }

  await migrerSiNecessaire(apiDir, resolvePhpBinary(), env);

  signalerEtapeDemarrage("Démarrage du service local…");

  for (let index = 0; index < NOMBRE_WORKERS; index++) {
    const worker = {
      port: API_PORT + 1 + index,
      proc: null,
      pret: false,
      actives: 0,
      redemarrages: 0,
    };
    workers.push(worker);
    demarrerWorker(worker);
  }

  await demarrerRepartiteur();

  const attentes = workers.map((worker) => attendreWorkerPret(worker));
  // Un serveur plus lent que les autres ne doit pas remonter en rejet non
  // géré une fois le premier prêt : son échec éventuel est déjà journalisé.
  for (const attente of attentes) attente.catch(() => {});

  try {
    await Promise.any(attentes);
  } catch {
    throw new Error("Le serveur local n'a pas démarré à temps.");
  }
}

const INTERVALLE_SYNC_MS = 5 * 60 * 1000;
const DELAI_PREMIERE_SYNC_MS = 20 * 1000;

let delaiPremiereSyncId = null;
let intervalleSyncId = null;
let syncEnCours = false;

/**
 * Une commande artisan, résolue une fois le processus terminé (jamais
 * rejetée : un échec de sync ne doit pas remonter plus haut que son propre
 * log) avec sa sortie standard, utile à `demarrerTelechargementFichiersEnArrierePlan()`
 * pour savoir quand arrêter de boucler sur `sync:fichiers`.
 */
function executerArtisan(commande, { arrierePlan = false } = {}) {
  const apiDir = resolveApiDir();
  const phpBinary = resolvePhpBinary();
  const phpArgs = resolvePhpArgsCommuns();
  const { env } = envInstanceLocale();

  return new Promise((resolve) => {
    const proc = spawn(phpBinary, [...phpArgs, "artisan", commande], {
      cwd: apiDir,
      env,
      stdio: "pipe",
      windowsHide: true,
    });

    // Une synchronisation que personne n'attend cède le processeur aux
    // serveurs PHP qui répondent à l'écran : sur un poste à deux cœurs, elle
    // ralentissait sinon chaque clic le temps de son passage.
    if (arrierePlan && proc.pid) {
      try {
        os.setPriority(proc.pid, os.constants.priority.PRIORITY_BELOW_NORMAL);
      } catch {
        // Processus déjà terminé, ou priorité refusée : sans conséquence.
      }
    }

    let stdout = "";
    proc.stdout.on("data", (chunk) => {
      stdout += chunk.toString("utf8");
    });
    proc.stderr.on("data", (chunk) => console.error(`[${commande}] ${chunk}`));
    proc.on("exit", (code) => {
      if (code !== 0)
        console.error(`[${commande}] terminé avec le code ${code}`);
      resolve({ stdout });
    });
    proc.on("error", (erreur) => {
      console.error(`[${commande}] impossible de démarrer : ${erreur.message}`);
      resolve({ stdout });
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
 *
 * Le premier cycle attend `DELAI_PREMIERE_SYNC_MS` : lancé dès l'ouverture,
 * il disputait le processeur et la base au tout premier écran, celui dont
 * l'utilisateur attend l'affichage.
 */
function lancerSyncPeriodique() {
  const cycle = () => synchroniserMaintenant({ arrierePlan: true });

  delaiPremiereSyncId = setTimeout(() => {
    delaiPremiereSyncId = null;
    cycle();
    intervalleSyncId = setInterval(cycle, INTERVALLE_SYNC_MS);
  }, DELAI_PREMIERE_SYNC_MS);
}

function arreterSyncPeriodique() {
  if (delaiPremiereSyncId) clearTimeout(delaiPremiereSyncId);
  if (intervalleSyncId) clearInterval(intervalleSyncId);
  delaiPremiereSyncId = null;
  intervalleSyncId = null;
}

async function synchroniserMaintenant({ arrierePlan = false } = {}) {
  if (syncEnCours) return false;
  syncEnCours = true;

  try {
    await executerArtisan("sync:pull", { arrierePlan });
    await executerArtisan("sync:push", { arrierePlan });
    // Rattrape ici les fichiers mis en file par ce `sync:pull` (et par le
    // clonage initial, cf. `lancerCloneInitial`) qui n'auraient pas encore
    // été absorbés par la boucle d'arrière-plan démarrée juste après lui —
    // no-op silencieux si la file est déjà vide.
    await executerArtisan("sync:fichiers", { arrierePlan });
    return true;
  } finally {
    syncEnCours = false;
  }
}

const INTERVALLE_FICHIERS_MS = 10 * 1000;
const CYCLES_FICHIERS_MAX = 60; // ~10 minutes avant de laisser la main au cycle périodique (5 min).

/**
 * Draine la file de fichiers en attente (photos élève/personnel…) juste
 * après le premier clonage, sans faire attendre l'utilisateur dessus : la
 * modale de clonage initial (cf. `lancerCloneInitial`) se ferme dès que les
 * DONNÉES sont là, les photos manquantes se complètent ensuite au fil de
 * l'eau pendant que l'utilisateur navigue déjà dans l'application.
 *
 * Bornée dans le temps plutôt que « jusqu'à la file vide » : un très grand
 * établissement (dizaines de milliers de photos) ne doit pas faire tourner
 * cette boucle indéfiniment en tâche de fond — passé `CYCLES_FICHIERS_MAX`
 * cycles, le reste continuera d'être absorbé, plus lentement, par le cycle
 * périodique habituel (`lancerSyncPeriodique`, toutes les 5 minutes).
 */
async function demarrerTelechargementFichiersEnArrierePlan() {
  for (let cycle = 0; cycle < CYCLES_FICHIERS_MAX; cycle++) {
    if (syncEnCours) {
      await new Promise((resolve) =>
        setTimeout(resolve, INTERVALLE_FICHIERS_MS),
      );
      continue;
    }

    syncEnCours = true;
    let fileVide = false;
    try {
      const { stdout } = await executerArtisan("sync:fichiers", {
        arrierePlan: true,
      });
      // Sortie de `SyncFichiers::handle()` : « 0 fichier(s) traité(s), ... »
      // quand la file était déjà vide à ce passage — inutile de reboucler.
      fileVide = /^0 fichier/m.test(stdout);
    } finally {
      syncEnCours = false;
    }

    if (fileVide) return;
    await new Promise((resolve) => setTimeout(resolve, INTERVALLE_FICHIERS_MS));
  }
}

/**
 * Premier clonage complet, juste après `POST /desktop/provisionner` (lequel
 * ne fait plus lui-même que créer la ligne `desktop_provisioning`, cf.
 * `DesktopProvisioningController::provisionner()`) : lance `sync:pull --json`
 * en processus séparé plutôt que via une requête HTTP au serveur PHP
 * embarqué, pour deux raisons — le serveur intégré de PHP (`php -S`) ne sert
 * qu'une requête à la fois, et une requête HTTP classique ne permettrait pas
 * de relayer une progression ligne par ligne au fil de l'eau. Chaque ligne
 * stdout est une des évènements JSON émis par `SyncPull::emettre()`
 * (`entite_debut`, `entite_fin`, `ecole_erreur`, `fin`…), relayée telle
 * quelle au renderer pour la modale de premier clonage.
 *
 * Partage `syncEnCours` avec la boucle périodique (`lancerSyncPeriodique`) :
 * les deux invoquent le même artisan sur la même base SQLite, un
 * chevauchement provoquerait des « database is locked » sporadiques.
 */
function lancerCloneInitial() {
  return new Promise((resolve, reject) => {
    if (syncEnCours) {
      reject(new Error("Une synchronisation est déjà en cours."));
      return;
    }

    syncEnCours = true;

    const apiDir = resolveApiDir();
    const phpBinary = resolvePhpBinary();
    const phpArgs = resolvePhpArgsCommuns();
    const { env } = envInstanceLocale();

    const proc = spawn(
      phpBinary,
      [...phpArgs, "artisan", "sync:pull", "--json"],
      { cwd: apiDir, env, stdio: "pipe" },
    );

    let resteStdout = "";
    let erreurStderr = "";

    proc.stdout.on("data", (chunk) => {
      resteStdout += chunk.toString("utf8");
      const lignes = resteStdout.split("\n");
      resteStdout = lignes.pop() ?? "";

      for (const ligne of lignes) {
        const texte = ligne.trim();
        if (!texte) continue;

        try {
          mainWindow?.webContents.send(
            "desktop:sync-progress",
            JSON.parse(texte),
          );
        } catch {
          // Une ligne de sortie non-JSON (avertissement PHP, etc.) : sans
          // intérêt pour la modale, mais ne doit pas interrompre le flux.
        }
      }
    });

    proc.stderr.on("data", (chunk) => {
      const texte = chunk.toString("utf8");
      erreurStderr = `${erreurStderr}${texte}`.slice(-2000);
      console.error(`[sync:pull] ${texte}`);
    });

    proc.on("error", (erreur) => {
      syncEnCours = false;
      reject(erreur);
    });

    proc.on("exit", (code) => {
      syncEnCours = false;
      if (code !== 0) {
        mainWindow?.webContents.send("desktop:sync-progress", {
          type: "sync_erreur",
          message:
            erreurStderr.includes("cURL error") ||
            erreurStderr.includes("Could not resolve host")
              ? "Connexion Internet interrompue. Les données déjà reçues sont conservées. Réessayez pour reprendre le téléchargement."
              : "Le téléchargement a été interrompu. Les données déjà reçues sont conservées. Réessayez pour reprendre.",
        });
      }
      // Ni attendu ni dans le bloc résolu ci-dessous : les données sont déjà
      // là, la modale de clonage peut se fermer immédiatement — les photos
      // manquantes se complètent seules pendant que l'utilisateur navigue
      // déjà dans l'application (cf. commentaire de la fonction).
      if (code === 0) demarrerTelechargementFichiersEnArrierePlan();
      resolve({ succes: code === 0 });
    });
  });
}

ipcMain.handle("desktop:run-initial-sync", () => lancerCloneInitial());

/**
 * Bouton « Synchroniser maintenant » du panneau de statut (renderer) : lance
 * le même `synchroniserMaintenant()` que la boucle périodique, un process
 * CLI séparé sans limite de temps — jamais l'ancienne route REST
 * `/desktop/synchroniser`, qui exécutait `sync:pull`/`sync:push` en ligne
 * dans la requête HTTP, à l'intérieur du même process que le serveur PHP
 * intégré. Une synchronisation volumineuse (plusieurs milliers d'élèves sur
 * plusieurs écoles) y heurtait la limite `max_execution_time` de ce process
 * web — observé en conditions réelles : « Maximum execution time of 30
 * seconds exceeded » — alors que le process CLI dédié, lui, tourne avec
 * `max_execution_time=0` (cf. `resolvePhpArgsCommuns()`).
 */
ipcMain.handle("desktop:sync-now", () => synchroniserMaintenant());

function creerMenuNatif() {
  const menu = Menu.buildFromTemplate([
    {
      label: "Elites School",
      submenu: [
        { role: "about", label: "À propos d'Elites School" },
        { type: "separator" },
        { role: "quit", label: "Quitter" },
      ],
    },
    {
      label: "Actions",
      submenu: [
        {
          label: "Synchroniser maintenant",
          click: async () => {
            const lancee = await synchroniserMaintenant();
            if (lancee) {
              dialog.showMessageBox({
                type: "info",
                title: "Synchronisation",
                message: "La synchronisation est terminée.",
              });
            }
          },
        },
        {
          label: "Rechercher des mises à jour",
          click: async () => {
            if (!app.isPackaged) {
              dialog.showMessageBox({
                type: "info",
                title: "Mise à jour",
                message:
                  "La recherche de mises à jour est disponible dans la version installée.",
              });
              return;
            }

            try {
              await autoUpdater.checkForUpdates();
            } catch (erreur) {
              dialog.showErrorBox(
                "Mise à jour",
                `La vérification a échoué.\n\n${erreur.message}`,
              );
            }
          },
        },
      ],
    },
  ]);

  Menu.setApplicationMenu(menu);
}

function arreterServeurPhp() {
  arretDemande = true;

  for (const worker of workers) {
    worker.proc?.kill();
    worker.proc = null;
    worker.pret = false;
  }

  repartiteur?.close();
  repartiteur = null;
}

/** Fenêtre principale — gardée pour y relayer les événements `electron-updater` (cf. `configurerAutoUpdate`). */
let mainWindow = null;

/** Dernière étape de démarrage annoncée, rejouée à l'écran d'attente s'il finit de charger après elle. */
let etapeDemarrage = "Démarrage…";

function executerCommandeEdition(action) {
  if (!mainWindow || mainWindow.isDestroyed()) return;
  if (action === "undo") mainWindow.webContents.undo();
  if (action === "redo") mainWindow.webContents.redo();
}

function signalerEtapeDemarrage(message) {
  etapeDemarrage = message;
  mainWindow?.webContents.send("desktop:startup-status", message);
}

/**
 * Ouvre la fenêtre sur l'écran d'attente (`splash.html`), sans attendre le
 * serveur PHP : l'application ne peut rien afficher d'utile avant lui, mais
 * une fenêtre qui n'apparaît qu'au bout de plusieurs secondes laissait croire
 * que le double-clic n'avait pas pris. `chargerApplication()` la bascule sur
 * la vraie interface une fois le serveur prêt.
 */
function createWindow() {
  const window = new BrowserWindow({
    width: 1440,
    height: 900,
    minWidth: 1100,
    minHeight: 700,
    frame: false,
    autoHideMenuBar: true,
    backgroundColor: "#140d1d",
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

  window.webContents.on("before-input-event", (event, input) => {
    const touche = input.key.toLowerCase();
    const modifieurPrincipal = input.control || input.meta;
    if (!modifieurPrincipal || input.alt) return;

    if (touche === "z" && !input.shift) {
      event.preventDefault();
      executerCommandeEdition("undo");
    }

    if (touche === "y" || (touche === "z" && input.shift)) {
      event.preventDefault();
      executerCommandeEdition("redo");
    }
  });

  window.webContents.on("did-finish-load", () => {
    window.webContents.send("desktop:startup-status", etapeDemarrage);
  });
  window.loadFile(path.join(__dirname, "splash.html"));

  mainWindow = window;
  window.on("closed", () => {
    if (mainWindow === window) mainWindow = null;
  });
}

/** Remplace l'écran d'attente par l'interface — sans effet si la fenêtre a été fermée entre-temps. */
function chargerApplication() {
  const dist = app.isPackaged
    ? path.join(process.resourcesPath, "web-dist")
    : path.join(__dirname, "../../dist");
  mainWindow?.loadFile(path.join(dist, "index.html"));
}

/** Relaie un statut au renderer — silencieux si aucune fenêtre n'est encore ouverte (ne devrait pas arriver, `createWindow()` précède toujours `configurerAutoUpdate()`). */
function envoyerStatutMiseAJour(statut) {
  mainWindow?.webContents.send("desktop:update-status", statut);
}

/**
 * Vérifie les mises à jour publiées sur les releases GitHub du dépôt
 * (config `build.publish` de package.json, lue depuis `app-update.yml`
 * embarqué au build — aucune configuration ici). Ignoré hors installation
 * packagée : en dev, il n'y a ni `app-update.yml` ni installeur NSIS à
 * remplacer, `checkForUpdates` échouerait pour rien à chaque lancement.
 */
function configurerAutoUpdate() {
  if (!app.isPackaged) {
    // Pas d'installeur NSIS à remplacer en dev (ni `app-update.yml`) : le
    // renderer doit quand même savoir pourquoi le panneau de statut ne dit
    // jamais rien, plutôt que de rester bloqué sur « Vérification... ».
    envoyerStatutMiseAJour({ etat: "non-empaquete" });
    return;
  }

  autoUpdater.autoDownload = true;
  autoUpdater.autoInstallOnAppQuit = true;

  autoUpdater.on("checking-for-update", () => {
    envoyerStatutMiseAJour({ etat: "verification" });
  });

  autoUpdater.on("update-available", (info) => {
    envoyerStatutMiseAJour({ etat: "disponible", version: info.version });
  });

  autoUpdater.on("update-not-available", (info) => {
    envoyerStatutMiseAJour({ etat: "a-jour", version: info.version });
  });

  autoUpdater.on("download-progress", (progres) => {
    envoyerStatutMiseAJour({
      etat: "telechargement",
      pourcentage: Math.round(progres.percent),
    });
  });

  autoUpdater.on("error", (erreur) => {
    console.error(
      "[update] échec de la vérification/du téléchargement",
      erreur,
    );
    envoyerStatutMiseAJour({ etat: "erreur", message: erreur.message });
  });

  // Téléchargée en tâche de fond, l'installation ne se fait qu'après accord
  // explicite : forcer un redémarrage sans prévenir couperait l'utilisateur
  // en pleine saisie (bulletins, absences...) sans sauvegarde préalable côté
  // SPA. Le renderer propose aussi son propre bouton « Redémarrer » (cf.
  // `desktop:quit-and-install`) : les deux mènent au même `quitAndInstall()`.
  autoUpdater.on("update-downloaded", (info) => {
    envoyerStatutMiseAJour({ etat: "telechargee", version: info.version });

    dialog
      .showMessageBox({
        type: "info",
        title: "Mise à jour disponible",
        message: `Une nouvelle version d'Elites School (${info.version}) a été téléchargée.`,
        detail: "Elle sera installée au prochain redémarrage de l'application.",
        buttons: ["Redémarrer maintenant", "Plus tard"],
        defaultId: 0,
        cancelId: 1,
      })
      .then(({ response }) => {
        if (response === 0) autoUpdater.quitAndInstall();
      });
  });

  const verifier = () =>
    autoUpdater.checkForUpdates().catch((erreur) => {
      console.error("[update] vérification impossible", erreur);
      envoyerStatutMiseAJour({ etat: "erreur", message: erreur.message });
    });

  verifier();
  // Poste desktop d'école : l'appli reste souvent ouverte toute la journée
  // sans jamais redémarrer, donc une seule vérification au lancement ne
  // suffit pas à faire arriver une mise à jour publiée en cours de journée.
  setInterval(verifier, 4 * 60 * 60 * 1000);
}

/**
 * Ponts IPC pour le panneau de statut desktop du renderer (version de l'app,
 * vérification manuelle des mises à jour, redémarrage pour installer) — cf.
 * `preload.cjs`. Enregistrés une fois, avant `app.whenReady()` n'a pas
 * d'importance ici : `ipcMain.handle` n'exige pas que l'app soit prête.
 */
ipcMain.handle("desktop:get-app-version", () => app.getVersion());

ipcMain.handle("desktop:window-minimize", () => {
  mainWindow?.minimize();
});

ipcMain.handle("desktop:window-toggle-maximize", () => {
  if (!mainWindow) return;
  if (mainWindow.isMaximized()) mainWindow.unmaximize();
  else mainWindow.maximize();
});

ipcMain.handle("desktop:window-close", () => {
  mainWindow?.close();
});

ipcMain.handle("desktop:edit-undo", () => {
  executerCommandeEdition("undo");
});

ipcMain.handle("desktop:edit-redo", () => {
  executerCommandeEdition("redo");
});

ipcMain.handle("desktop:check-for-updates", async () => {
  if (!app.isPackaged) return { skipped: true };

  try {
    await autoUpdater.checkForUpdates();
    return { skipped: false };
  } catch (erreur) {
    return { skipped: false, error: erreur.message };
  }
});

ipcMain.handle("desktop:quit-and-install", () => {
  autoUpdater.quitAndInstall();
});

app.whenReady().then(async () => {
  session.defaultSession.webRequest.onHeadersReceived((details, callback) => {
    callback({
      responseHeaders: {
        ...details.responseHeaders,
        // `frame-src` distinct de `default-src` : les aperçus PDF chargés
        // dans un <iframe> utilisent des URL `data:` (desktop) ou `blob:`
        // (web). Chromium ouvre ensuite son lecteur PDF interne via
        // `chrome-extension:`, qui doit également être autorisé.
        "Content-Security-Policy": [
          "default-src 'self' 'unsafe-inline' data: blob: http: https:; frame-src 'self' data: blob: chrome-extension:",
        ],
      },
    });
  });

  Menu.setApplicationMenu(null);
  createWindow();

  try {
    await demarrerServeurPhp();
  } catch (erreur) {
    console.error(erreur);
    // Sans ce message, l'utilisateur ne voit qu'un écran de connexion cassé
    // (erreurs réseau silencieuses dans les DevTools, jamais ouvertes en
    // usage normal) sans aucun indice sur ce qui a échoué.
    dialog.showErrorBox(
      "Elites School — démarrage impossible",
      "Le serveur local n'a pas pu démarrer.\n\n" +
        "Cause fréquente : un antivirus qui analyse encore les fichiers de l'application " +
        "lors de sa toute première exécution. Fermez cette fenêtre et relancez Elites School — " +
        "les lancements suivants sont nettement plus rapides.\n\n" +
        `Détail technique : ${detailErreurCommande(erreur)}`,
    );
  }

  // Fenêtre fermée pendant le démarrage : l'application se termine déjà
  // (cf. `window-all-closed`), rien à charger ni à planifier.
  if (arretDemande) return;

  chargerApplication();
  configurerAutoUpdate();
  // Ni attendu ni dans le bloc try/catch ci-dessus : un aléa réseau au tout
  // premier cycle ne doit pas empêcher la fenêtre de s'ouvrir, et chaque
  // commande gère déjà elle-même son propre échec (voir `executerArtisan`).
  lancerSyncPeriodique();
  app.on("activate", () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
      chargerApplication();
    }
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
