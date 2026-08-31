/**
 * Prépare une copie portable de PHP dans `desktop/resources/php`, embarquée
 * par l'installeur (cf. `build-installer.cjs`) pour que l'application
 * fonctionne sur un poste qui n'a PHP nulle part d'installé.
 *
 * Source : `ELITES_PHP_SOURCE` si fourni, sinon `C:\php` (poste de
 * développement actuel). Le binaire copié n'est PAS committé dans le dépôt
 * (cf. `.gitignore`) — ce script le régénère à chaque build.
 */
const { cpSync, existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync, rmSync } = require("node:fs");
const path = require("node:path");
const https = require("node:https");

const source = process.env.ELITES_PHP_SOURCE || "C:\\php";
const destination = path.resolve(__dirname, "..", "resources", "php");

/**
 * Bundle de certificats racine Mozilla, celui que distribue officiellement
 * curl : sans lui, `curl`/`openssl` sous Windows ne peuvent valider AUCUNE
 * connexion HTTPS sortante (erreur « cURL 60 : unable to get local issuer
 * certificate ») — un poste de développement s'en sort sans y penser parce
 * que sa propre installation PHP en a déjà un de configuré, mais une copie
 * portable nue n'en a aucun. Sans ce fichier, `sync:pull`/`sync:push` (les
 * seuls appels HTTPS sortants de l'app, faits par ce PHP embarqué) échouent
 * silencieusement — observé en conditions réelles : poste provisionné,
 * tableau de bord entièrement à zéro.
 */
const CACERT_URL = "https://curl.se/ca/cacert.pem";

function telechargerCacert(destinationFichier) {
  return new Promise((resolve, reject) => {
    https.get(CACERT_URL, (reponse) => {
      if (reponse.statusCode !== 200) {
        reponse.resume();
        reject(new Error(`Téléchargement du bundle CA échoué : HTTP ${reponse.statusCode}`));
        return;
      }
      const fichier = require("node:fs").createWriteStream(destinationFichier);
      reponse.pipe(fichier);
      fichier.on("finish", () => fichier.close(resolve));
      fichier.on("error", reject);
    }).on("error", reject);
  });
}

/**
 * Extensions dont l'application a réellement besoin (cf. audit de
 * portabilité SQLite du plan desktop) : `pdo_mysql`/`pdo_pgsql`/`pgsql`/`ftp`
 * du poste de développement ne servent à rien pour une instance locale
 * SQLite, autant ne pas les charger au démarrage.
 */
const EXTENSIONS_REQUISES = [
  "curl", "exif", "fileinfo", "gd", "intl", "mbstring", "openssl",
  "pdo_sqlite", "sodium", "sqlite3", "zip",
];

function reecrirePhpIni(iniPath) {
  const original = readFileSync(iniPath, "utf8");

  // `php.ini-production` part de zéro extension activée (tout est commenté
  // par défaut) : chaque ligne `extension=xxx`, commentée ou non, est donc
  // retranchée puis reconstruite selon qu'elle figure dans la liste requise —
  // pas seulement désactivée quand elle y est absente.
  const lignes = original.split(/\r?\n/).map((ligne) => {
    const correspondance = ligne.match(/^;?\s*extension\s*=\s*(\w+)/);
    if (!correspondance) return ligne;

    const nom = correspondance[1];
    return EXTENSIONS_REQUISES.includes(nom) ? `extension=${nom}` : `;extension=${nom}`;
  });

  let ini = lignes.join("\n");

  // Chemin explicite plutôt que la résolution implicite de PHP (qui varie
  // selon la version et l'endroit d'où le binaire est lancé) : le dossier
  // `ext` est toujours à côté de ce `php.ini`, quel que soit le `cwd` du
  // process PHP démarré par Electron (celui de l'app Laravel, pas celui-ci).
  ini = ini.replace(/^;?\s*extension_dir\s*=.*$/m, 'extension_dir = "ext"');

  writeFileSync(iniPath, ini, "utf8");
}

(async () => {
  if (!existsSync(source)) {
    console.error(`[bundle-php] Source PHP introuvable : ${source} (définir ELITES_PHP_SOURCE ?)`);
    process.exit(1);
  }

  console.log(`[bundle-php] copie de ${source} vers ${destination}`);
  rmSync(destination, { recursive: true, force: true });
  mkdirSync(destination, { recursive: true });

  // On ne prend que ce qui sert réellement à exécuter du PHP en CLI : pas
  // `dev/`, `extras/`, la doc, ni les binaires web (php-cgi, php-win) inutiles
  // pour un serveur intégré lancé en `-S`.
  for (const entree of ["php.exe", "php.ini-production", "ext"]) {
    cpSync(path.join(source, entree), path.join(destination, entree), { recursive: true });
  }
  // Toutes les DLL de dépendances à la racine (icu*, libssl, libcrypto,
  // libsqlite3, libsodium...) : les extensions activées en ont besoin au
  // chargement, et il est plus sûr de toutes les prendre que de deviner
  // lesquelles précisément chaque extension requiert.
  for (const entree of readdirSync(source)) {
    if (entree.endsWith(".dll")) {
      cpSync(path.join(source, entree), path.join(destination, entree));
    }
  }

  const cacertDestination = path.join(destination, "cacert.pem");
  const cacertLocal = process.env.ELITES_CACERT_PATH;
  if (cacertLocal && existsSync(cacertLocal)) {
    console.log(`[bundle-php] copie du bundle CA local (${cacertLocal})`);
    cpSync(cacertLocal, cacertDestination);
  } else {
    console.log(`[bundle-php] téléchargement du bundle CA (${CACERT_URL})`);
    await telechargerCacert(cacertDestination);
  }

  const iniDestination = path.join(destination, "php.ini");
  cpSync(path.join(destination, "php.ini-production"), iniDestination);
  reecrirePhpIni(iniDestination);

  console.log("[bundle-php] terminé");
})().catch((erreur) => {
  console.error("[bundle-php]", erreur.message);
  process.exit(1);
});
