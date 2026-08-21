const { cpSync, existsSync, mkdirSync, rmSync } = require("node:fs");
const { spawnSync } = require("node:child_process");
const path = require("node:path");
const { createPackage } = require("@electron/asar");

const root = path.resolve(__dirname, "..");
const unpackedOutput = path.join(root, "build-unpacked");
const stableOutput = path.join(root, "prepackaged-final");
const installerOutput = path.join(root, "dist");
const executableName = "Elites School.exe";
const webDist = path.resolve(root, "..", "dist");

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
  cpSync(
    path.join(root, "node_modules", "better-sqlite3", "build"),
    path.join(appStage, "node_modules", "better-sqlite3", "build"),
    { recursive: true },
  );
  mkdirSync(path.dirname(appAsar), { recursive: true });
  console.log("[desktop] creation de app.asar");
  await createPackage(appStage, appAsar);
  console.log("[desktop] copie de web-dist");
  cpSync(webDist, packagedWeb, { recursive: true });
  rmSync(appStage, { recursive: true, force: true });

  console.log("[desktop] creation d'une copie stable pour NSIS");
  cpSync(unpacked, stableOutput, { recursive: true });

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
