const { contextBridge } = require("electron");

/**
 * Surface volontairement réduite : depuis que l'application Laravel tourne
 * en local (cf. main.cjs), le renderer parle directement à l'API locale en
 * HTTP normal — plus besoin de cache ni de file d'attente côté JS, c'est
 * Laravel qui gère tout ça (SQLite locale, `sync_outbox`).
 *
 * `apiBaseUrl` est une constante connue au chargement du preload (le port
 * est fixe, cf. `API_PORT` dans main.cjs) : pas d'aller-retour IPC
 * nécessaire pour la lire.
 */
contextBridge.exposeInMainWorld("desktop", {
  apiBaseUrl: "http://127.0.0.1:8973/api/v1",
});
