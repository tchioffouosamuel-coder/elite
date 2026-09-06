/// <reference types="vite/client" />

/** Statuts relayés depuis `electron-updater` (cf. `web/desktop/src/main.cjs`, `configurerAutoUpdate`). */
type StatutMiseAJourDesktop =
  | { etat: "non-empaquete" }
  | { etat: "verification" }
  | { etat: "disponible"; version: string }
  | { etat: "a-jour"; version: string }
  | { etat: "telechargement"; pourcentage: number }
  | { etat: "telechargee"; version: string }
  | { etat: "erreur"; message: string };

interface Window {
  /**
   * Présent uniquement dans le client desktop (cf. `web/desktop/src/preload.cjs`) :
   * l'application Laravel tourne alors en local (PHP + SQLite), et
   * `apiBaseUrl` pointe vers cette instance plutôt que vers `VITE_API_URL`.
   */
  desktop?: {
    apiBaseUrl: string;
    getAppVersion: () => Promise<string>;
    checkForUpdates: () => Promise<{ skipped: boolean; error?: string }>;
    quitAndInstall: () => Promise<void>;
    onUpdateStatus: (callback: (statut: StatutMiseAJourDesktop) => void) => () => void;
  };
}
