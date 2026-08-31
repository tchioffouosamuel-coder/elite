/// <reference types="vite/client" />

interface Window {
  /**
   * Présent uniquement dans le client desktop (cf. `web/desktop/src/preload.cjs`) :
   * l'application Laravel tourne alors en local (PHP + SQLite), et
   * `apiBaseUrl` pointe vers cette instance plutôt que vers `VITE_API_URL`.
   */
  desktop?: {
    apiBaseUrl: string;
  };
}
