const { app, BrowserWindow, ipcMain, session } = require("electron");
const path = require("node:path");
const Database = require("better-sqlite3");
const { autoUpdater } = require("electron-updater");

const database = new Database(
  path.join(app.getPath("userData"), "elites-school.sqlite"),
);
database.pragma("journal_mode = WAL");
database.exec(`
  CREATE TABLE IF NOT EXISTS response_cache (
    cache_key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL
  );
  CREATE TABLE IF NOT EXISTS sync_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    method TEXT NOT NULL,
    url TEXT NOT NULL,
    data TEXT,
    headers TEXT,
    created_at TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0
  );
  CREATE TABLE IF NOT EXISTS sync_state (
    key TEXT PRIMARY KEY,
    value TEXT
  );
  CREATE TABLE IF NOT EXISTS sync_entities (
    entity TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    PRIMARY KEY (entity, entity_id)
  );
`);

function registerIpc() {
  ipcMain.handle("desktop:cache-get", (_event, key) => {
    const row = database
      .prepare("SELECT value FROM response_cache WHERE cache_key = ?")
      .get(key);
    return row ? JSON.parse(row.value) : null;
  });
  ipcMain.handle("desktop:cache-put", (_event, key, value) => {
    database
      .prepare(
        "INSERT OR REPLACE INTO response_cache (cache_key, value, updated_at) VALUES (?, ?, ?)",
      )
      .run(key, JSON.stringify(value), new Date().toISOString());
  });
  ipcMain.handle("desktop:enqueue", (_event, request) => {
    database
      .prepare(
        "INSERT INTO sync_queue (method, url, data, headers, created_at) VALUES (?, ?, ?, ?, ?)",
      )
      .run(
        request.method,
        request.url,
        JSON.stringify(request.data ?? null),
        JSON.stringify(request.headers ?? {}),
        new Date().toISOString(),
      );
  });
  ipcMain.handle("desktop:sync", async (_event, options) => {
    const pending = database
      .prepare("SELECT * FROM sync_queue ORDER BY id")
      .all();
    let synced = 0;
    for (const item of pending) {
      try {
        const response = await fetch(new URL(item.url, options.baseUrl), {
          method: item.method.toUpperCase(),
          headers: {
            Accept: "application/json",
            "X-Locale": options.locale,
            ...(options.schoolId
              ? { "X-School-Id": String(options.schoolId) }
              : {}),
            ...JSON.parse(item.headers || "{}"),
            Authorization: `Bearer ${options.token}`,
            ...(item.data ? { "Content-Type": "application/json" } : {}),
          },
          body: item.data ? item.data : undefined,
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        database.prepare("DELETE FROM sync_queue WHERE id = ?").run(item.id);
        synced += 1;
      } catch {
        database
          .prepare("UPDATE sync_queue SET attempts = attempts + 1 WHERE id = ?")
          .run(item.id);
        break;
      }
    }
    return synced;
  });
  ipcMain.handle("desktop:bootstrap", async (_event, options) => {
    let cursor =
      database
        .prepare("SELECT value FROM sync_state WHERE key = 'cursor'")
        .get()?.value ?? null;
    let complete = false;
    let passes = 0;
    let entities = 0;

    while (!complete && passes < 100) {
      const baseUrl = options.baseUrl.endsWith("/")
        ? options.baseUrl
        : `${options.baseUrl}/`;
      const url = new URL("sync", baseUrl);
      if (cursor) url.searchParams.set("depuis", cursor);
      const response = await fetch(url, {
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${options.token}`,
          "X-Locale": options.locale,
          ...(options.schoolId
            ? { "X-School-Id": String(options.schoolId) }
            : {}),
        },
      });
      if (!response.ok) throw new Error(`Bootstrap HTTP ${response.status}`);
      const payload = await response.json();
      const data = payload.data ?? {};
      const transaction = database.transaction(() => {
        for (const [entity, rows] of Object.entries(data.donnees ?? {})) {
          for (const row of rows) {
            if (!row || row.id === undefined || row.id === null) continue;
            database
              .prepare(
                "INSERT OR REPLACE INTO sync_entities (entity, entity_id, value, updated_at) VALUES (?, ?, ?, ?)",
              )
              .run(
                entity,
                String(row.id),
                JSON.stringify(row),
                new Date().toISOString(),
              );
            entities += 1;
          }
        }
        for (const deletion of data.suppressions ?? []) {
          database
            .prepare(
              "DELETE FROM sync_entities WHERE entity = ? AND entity_id = ?",
            )
            .run(deletion.entite, String(deletion.id));
        }
        cursor = data.curseur ?? cursor;
        database
          .prepare(
            "INSERT OR REPLACE INTO sync_state (key, value) VALUES ('cursor', ?)",
          )
          .run(cursor);
      });
      transaction();
      complete = data.complet !== false;
      passes += 1;
    }
    return { passes, entities };
  });
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
  const dist = app.isPackaged
    ? path.join(process.resourcesPath, "web-dist")
    : path.join(__dirname, "../../dist");
  window.loadFile(path.join(dist, "index.html"));
}

app.whenReady().then(() => {
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
  registerIpc();
  createWindow();
  if (app.isPackaged && process.env.NODE_ENV === "production") {
    autoUpdater.checkForUpdatesAndNotify().catch(() => {});
  }
  app.on("activate", () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on("window-all-closed", () => {
  if (process.platform !== "darwin") app.quit();
});
