const { contextBridge, ipcRenderer } = require("electron");

/**
 * Surface volontairement réduite : depuis que l'application Laravel tourne
 * en local (cf. main.cjs), le renderer parle directement à l'API locale en
 * HTTP normal — plus besoin de cache ni de file d'attente côté JS, c'est
 * Laravel qui gère tout ça (SQLite locale, `sync_outbox`).
 *
 * `apiBaseUrl` est une constante connue au chargement du preload (le port
 * est fixe, cf. `API_PORT` dans main.cjs) : pas d'aller-retour IPC
 * nécessaire pour la lire.
 *
 * Les méthodes ci-dessous, elles, touchent au processus principal
 * (`electron-updater`, version de l'app) — impossibles à obtenir en HTTP
 * puisqu'elles ne concernent pas l'API Laravel mais l'exécutable Electron
 * lui-même.
 */
contextBridge.exposeInMainWorld("desktop", {
  apiBaseUrl: "http://127.0.0.1:8973/api/v1",

  getAppVersion: () => ipcRenderer.invoke("desktop:get-app-version"),

  /** Déclenche une vérification manuelle (bouton « Vérifier maintenant ») — no-op en dev, cf. main.cjs. */
  checkForUpdates: () => ipcRenderer.invoke("desktop:check-for-updates"),

  /** Redémarre l'application pour appliquer une mise à jour déjà téléchargée. */
  quitAndInstall: () => ipcRenderer.invoke("desktop:quit-and-install"),

  /**
   * Abonnement aux événements `electron-updater` relayés depuis le processus
   * principal (`configurerAutoUpdate()` dans main.cjs) — retourne la fonction
   * de désabonnement, à appeler au démontage du composant qui écoute.
   */
  onUpdateStatus: (callback) => {
    const listener = (_event, statut) => callback(statut);
    ipcRenderer.on("desktop:update-status", listener);
    return () => ipcRenderer.removeListener("desktop:update-status", listener);
  },
});
