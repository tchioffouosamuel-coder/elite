const { cpSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } = require("node:fs");
const { spawnSync } = require("node:child_process");
const path = require("node:path");
const { createPackage } = require("@electron/asar");
const { NtExecutable, NtExecutableResource, Data, Resource } = require("resedit");

const root = path.resolve(__dirname, "..");
const unpackedOutput = path.join(root, "build-unpacked");
const stableOutput = path.join(root, "prepackaged-final");
const installerOutput = path.join(root, "dist");
const executableName = "Elites School.exe";
const webDist = path.resolve(root, "..", "dist");
const apiSource = path.resolve(root, "..", "..", "api");
const phpBundleSource = path.join(root, "resources", "php");
const iconSource = path.join(root, "build", "icon.ico");
const packageJson = JSON.parse(readFileSync(path.join(root, "package.json"), "utf8"));

/**
 * `--prepackaged` court-circuite l'étape normale d'electron-builder qui
 * embarque l'icône et les métadonnées dans le binaire (rcedit) — ici on
 * part d'un `electron.exe` nu simplement renommé. `resedit` (déjà présent
 * en dépendance transitive d'`app-builder-lib`, qui l'utilise pour la même
 * chose) fait le même travail en pur JS, sans binaire natif à invoquer.
 */
function embarquerIconeEtVersion(executablePath) {
  if (!existsSync(iconSource)) {
    console.warn(`[desktop] icône introuvable (${iconSource}) — icône Electron par défaut conservée.`);
    return;
  }

  const executable = NtExecutable.from(readFileSync(executablePath));
  const res = NtExecutableResource.from(executable);

  const viList = Resource.VersionInfo.fromEntries(res.entries);
  const vi = viList.length > 0 ? viList[0] : Resource.VersionInfo.createEmpty();
  const langues = vi.getAllLanguagesForStringValues();
  const langue = langues.length > 0 ? langues[0] : { lang: 0x0409, codepage: 1200 };

  vi.setStringValues(langue, {
    ProductName: packageJson.build?.productName ?? packageJson.name,
    FileDescription: packageJson.description ?? packageJson.build?.productName,
    CompanyName: packageJson.author,
    LegalCopyright: `Copyright © ${new Date().getFullYear()} ${packageJson.author}`,
  });
  vi.setFileVersion(packageJson.version);
  vi.setProductVersion(packageJson.version);
  vi.outputToResourceEntries(res.entries);

  const iconFile = Data.IconFile.from(readFileSync(iconSource));
  Resource.IconGroupEntry.replaceIconsForResource(res.entries, 1, langue.lang, iconFile.icons.map((i) => i.data));

  res.outputResource(executable);
  writeFileSync(executablePath, Buffer.from(executable.generate()));
  console.log("[desktop] icône et métadonnées intégrées à", path.basename(executablePath));
}

/**
 * Fichiers de `api/` qui n'ont rien à faire dans l'installeur : dépendances
 * de dev (réinstallées ci-dessous sans elles), tests, historique git, et
 * tout état d'exécution propre au poste de développement — une base SQLite
 * de dev embarquée écraserait sinon celle, vide, que chaque installation
 * doit créer elle-même au premier lancement (cf. `main.cjs`).
 */
const API_EXCLUSIONS = new Set(["vendor", "tests", ".git", ".github", "node_modules", "storage"]);

function copierApi(destination) {
  cpSync(apiSource, destination, {
    recursive: true,
    filter: (src) => {
      const relatif = path.relative(apiSource, src);
      if (relatif === "") return true;
      const premierSegment = relatif.split(path.sep)[0];
      if (API_EXCLUSIONS.has(premierSegment)) return false;
      if (relatif.startsWith(path.join("database", "database.sqlite"))) return false;
      return true;
    },
  });

  // Ossature vide de `storage/` : Laravel plante si ces dossiers n'existent
  // pas, même vides (logs, cache de vues Blade, sessions, fichiers publics).
  for (const sous of ["app/public", "framework/cache", "framework/sessions", "framework/views", "logs"]) {
    mkdirSync(path.join(destination, "storage", sous), { recursive: true });
  }

  // `composer install` peut sortir en succès (code 0) tout en ayant produit
  // un `vendor/` incomplet — observé en conditions réelles, deux fois de
  // suite, sur un runner GitHub Actions Windows : un fichier de
  // `laravel/framework` manquait silencieusement à la sortie de l'archive
  // ZIP (le paquet upstream, lui, le contient bel et bien — vérifié), et
  // l'installeur packagé plantait au tout premier lancement de chaque poste
  // avec une erreur d'autoload, sans que rien ne l'ait détecté avant
  // publication. Récurrent plutôt qu'un simple coup de malchance réseau
  // isolé : probablement une course avec l'antivirus temps réel du runner
  // (verrou de fichier pendant l'extraction) plutôt qu'une corruption de
  // téléchargement — recommencer l'extraction quelques instants plus tard
  // suffit en pratique à s'en sortir, d'où la boucle ci-dessous plutôt qu'un
  // unique essai.
  const composer = process.platform === "win32" ? "composer.bat" : "composer";
  const TENTATIVES_MAX = 3;

  for (let tentative = 1; tentative <= TENTATIVES_MAX; tentative++) {
    console.log(`[desktop] composer install --no-dev dans la copie embarquée (tentative ${tentative}/${TENTATIVES_MAX})`);
    rmSync(path.join(destination, "vendor"), { recursive: true, force: true });
    const install = spawnSync(
      composer,
      ["install", "--no-dev", "--optimize-autoloader", "--no-interaction"],
      { cwd: destination, stdio: "inherit", shell: true },
    );
    if (install.status !== 0) {
      if (tentative === TENTATIVES_MAX) {
        throw new Error("composer install a échoué pour la copie embarquée de l'API.");
      }
      continue;
    }

    // `artisan --version` force à charger entièrement le framework (autoload
    // de toutes les classes du noyau) : un vendor tronqué y échoue
    // immédiatement, un vendor complet répond en une fraction de seconde.
    console.log("[desktop] vérification du vendor embarqué (artisan --version)");
    const verification = spawnSync("php", ["artisan", "--version"], {
      cwd: destination,
      stdio: "inherit",
      shell: true,
    });

    if (verification.status === 0) {
      break;
    }

    if (tentative === TENTATIVES_MAX) {
      throw new Error(
        "Le vendor composé pour l'API embarquée reste corrompu ou incomplet " +
        `après ${TENTATIVES_MAX} tentatives (\`artisan --version\` échoue juste après ` +
        "un `composer install` pourtant réussi) — build interrompu plutôt que de " +
        "publier un installeur cassé.",
      );
    }

    console.warn(`[desktop] vendor incomplet à la tentative ${tentative}, nouvel essai...`);
  }

  // Pas de `config:cache`/`route:cache` ici : la configuration (DB_DATABASE,
  // APP_KEY...) est injectée par variables d'environnement à chaque
  // lancement par `main.cjs`, propres à CE poste — la figer au moment du
  // build embarquerait celles de la machine qui compile l'installeur.
}

/**
 * `electron-updater` lit ce fichier, embarqué dans `resources/` de l'app
 * installée, pour savoir où chercher les mises à jour (cf. le commentaire de
 * `configurerAutoUpdate()` dans `src/main.cjs`). electron-builder l'écrit
 * normalement lui-même via son hook `afterPack` — mais `--prepackaged`
 * (cf. `embarquerIconeEtVersion()` plus haut) fait retourner `doPack()`
 * avant même que cet évènement ne soit émis, donc le hook ne se déclenche
 * jamais et le fichier n'existe jamais dans l'installeur produit ici. On le
 * régénère donc nous-mêmes, à partir de `build.publish` de `package.json` —
 * seul le provider GitHub (celui réellement utilisé, cf. `release.ps1`) est
 * géré, un format minimal suffisant à `electron-updater` pour un dépôt
 * public sans canal ni jeton spécifique.
 */
function ecrireAppUpdateYml(resourcesDir) {
  const publish = packageJson.build?.publish;
  if (!publish || publish.provider !== "github" || !publish.owner || !publish.repo) {
    console.warn(
      "[desktop] build.publish (provider github, owner, repo) absent ou incomplet dans package.json : " +
      "app-update.yml non généré, l'auto-update sera inactif sur cet installeur.",
    );
    return;
  }

  const updaterCacheDirName = `${packageJson.name.toLowerCase()}-updater`;
  const contenu = [
    `provider: ${publish.provider}`,
    `owner: ${publish.owner}`,
    `repo: ${publish.repo}`,
    `updaterCacheDirName: ${updaterCacheDirName}`,
    "",
  ].join("\n");

  writeFileSync(path.join(resourcesDir, "app-update.yml"), contenu, "utf8");
  console.log("[desktop] app-update.yml généré pour electron-updater");
}

function runBuilder(args) {
  const cli = path.join(root, "node_modules", "electron-builder", "cli.js");
  const result = spawnSync(process.execPath, [cli, ...args], {
    cwd: root,
    stdio: "inherit",
    shell: false,
  });
  return result.status ?? 1;
}

(async () => {
  console.log("[desktop] nettoyage des sorties");
  rmSync(unpackedOutput, { recursive: true, force: true });
  rmSync(stableOutput, { recursive: true, force: true });
  rmSync(installerOutput, { recursive: true, force: true });

  const unpacked = path.join(unpackedOutput, "win-unpacked");
  const electronDist = path.join(root, "node_modules", "electron", "dist");
  console.log("[desktop] copie du runtime Electron");
  cpSync(electronDist, unpacked, { recursive: true });

  const rawExecutable = path.join(unpacked, "electron.exe");
  const namedExecutable = path.join(unpacked, executableName);
  if (existsSync(rawExecutable) && !existsSync(namedExecutable)) {
    cpSync(rawExecutable, namedExecutable);
    rmSync(rawExecutable, { force: true });
  }
  embarquerIconeEtVersion(namedExecutable);

  const appStage = path.join(root, "app-stage");
  const appAsar = path.join(unpacked, "resources", "app.asar");
  const packagedWeb = path.join(unpacked, "resources", "web-dist");
  rmSync(appStage, { recursive: true, force: true });
  rmSync(appAsar, { force: true });
  rmSync(packagedWeb, { recursive: true, force: true });
  console.log("[desktop] preparation des dependances runtime");
  mkdirSync(path.join(appStage, "src"), { recursive: true });
  cpSync(path.join(root, "src"), path.join(appStage, "src"), {
    recursive: true,
  });
  cpSync(path.join(root, "package.json"), path.join(appStage, "package.json"));
  const npmCommand = process.platform === "win32" ? "npm.cmd" : "npm";
  const install = spawnSync(
    npmCommand,
    ["install", "--omit=dev", "--ignore-scripts", "--no-audit", "--no-fund"],
    { cwd: appStage, stdio: "inherit", shell: true },
  );
  if (install.status !== 0) {
    process.exit(install.status ?? 1);
  }
  mkdirSync(path.dirname(appAsar), { recursive: true });
  console.log("[desktop] creation de app.asar");
  await createPackage(appStage, appAsar);
  console.log("[desktop] copie de web-dist");
  cpSync(webDist, packagedWeb, { recursive: true });
  rmSync(appStage, { recursive: true, force: true });

  const packagedApi = path.join(unpacked, "resources", "api");
  const packagedPhp = path.join(unpacked, "resources", "php");
  rmSync(packagedApi, { recursive: true, force: true });
  rmSync(packagedPhp, { recursive: true, force: true });

  console.log("[desktop] copie de l'API (composer install --no-dev)");
  copierApi(packagedApi);

  if (!existsSync(phpBundleSource)) {
    throw new Error(
      `PHP portable introuvable (${phpBundleSource}) — lancer d'abord "node scripts/bundle-php.cjs".`,
    );
  }
  console.log("[desktop] copie du PHP portable");
  cpSync(phpBundleSource, packagedPhp, { recursive: true });

  console.log("[desktop] creation d'une copie stable pour NSIS");
  cpSync(unpacked, stableOutput, { recursive: true });
  ecrireAppUpdateYml(path.join(stableOutput, "resources"));

  // electron-builder ne publie plus lui-même la Release GitHub : ses uploads
  // d'assets tournent en parallèle et chacun tente d'assurer que la release
  // existe, ce qui a fini par créer deux releases distinctes pour le même
  // tag (une seule complète, l'autre orpheline — parfois même sans le bon
  // nom de tag, cf. la release "untagged-" produite pour la v1.2.5). La
  // publication se fait donc désormais ailleurs (`gh release create`, un
  // seul appel atomique), une fois le build terminé — cf. le workflow CI.
  console.log("[desktop] creation du setup NSIS");
  const status = runBuilder([
    "--win",
    "nsis",
    "--prepackaged",
    stableOutput,
    "--publish",
    "never",
    `--config.directories.output=${installerOutput}`,
  ]);

  process.exit(status);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
