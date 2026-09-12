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

/** Un évènement JSON par ligne, émis par `sync:pull --json` (cf. `SyncPull::emettre()`) et relayé tel quel par `main.cjs`. */
type EvenementSyncProgress =
  | { type: "debut"; ecoles: number; entites_par_ecole: number }
  | { type: "ecole_debut"; school_id: number; nom: string | null; index: number; total: number }
  | { type: "ecole_fin"; school_id: number; index: number; total: number }
  | { type: "entite_debut"; school_id: number; cle: string; etape: number; total_etapes: number }
  | { type: "entite_progres"; school_id: number; cle: string; lignes: number }
  | { type: "entite_fin"; school_id: number; cle: string; etape: number; total_etapes: number; lignes: number }
  | { type: "ecole_erreur"; school_id: number; message: string }
  | { type: "clonage_initial_complet"; user_id: number }
  | { type: "fin"; echec: boolean };

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
    /** Premier clonage complet — cf. `PremiereSynchronisationModal`. Brancher `onSyncProgress` AVANT d'appeler cette méthode. */
    runInitialSync: () => Promise<{ succes: boolean }>;
    onSyncProgress: (callback: (evenement: EvenementSyncProgress) => void) => () => void;
  };
}
