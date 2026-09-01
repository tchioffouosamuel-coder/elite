import axios from "axios";
import type { AxiosError } from "axios";

declare module "axios" {
  export interface AxiosRequestConfig {
    /** N'affiche pas la modale « Permission manquante » sur un 403 — pour un appel dont l'échec ne doit pas interrompre l'utilisateur (ex. remplir un sélecteur optionnel). */
    silent403?: boolean;
  }
}
import { useAuthStore } from "@/shared/store/authStore";
import { useUiStore } from "@/shared/store/uiStore";
import { permissionManquante } from "@/shared/lib/alertes";
import type { ApiError } from "@/shared/types/api";

/**
 * En desktop, l'API n'est plus distante : `window.desktop.apiBaseUrl` pointe
 * vers l'instance Laravel locale (PHP + SQLite) que `main.cjs` démarre au
 * lancement de l'application — cf. le plan de synchronisation offline. Le
 * navigateur web classique garde `VITE_API_URL`.
 */
export const API_BASE_URL =
  window.desktop?.apiBaseUrl ??
  import.meta.env.VITE_API_URL ??
  "http://127.0.0.1:8000/api/v1";

export const http = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30 * 60 * 1000,
  headers: { Accept: "application/json" },
});

http.interceptors.request.use((config) => {
  const { token, user, activeSchoolId } = useAuthStore.getState();
  const { locale } = useUiStore.getState();

  if (token) config.headers.Authorization = `Bearer ${token}`;

  // Un appel peut fixer son propre X-School-Id (ex. consulter les classes
  // d'une autre école du complexe avant un transfert) sans faire basculer
  // tout le contexte applicatif : on ne l'écrase alors pas.
  //
  // `activeSchoolId` à `null` pour un super admin signifie le mode agrégé
  // (tout le complexe) : on omet alors volontairement l'en-tête, plutôt que
  // de retomber sur `user.school_id`, sans quoi l'API ne verrait jamais ce
  // mode et resterait bornée à une seule école.
  const schoolId = user?.is_super_admin
    ? activeSchoolId
    : (activeSchoolId ?? user?.school_id);
  if (schoolId && !config.headers["X-School-Id"])
    config.headers["X-School-Id"] = String(schoolId);
  config.headers["X-Locale"] = locale;

  return config;
});

http.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    const config = error.config;

    // Un jeton Sanctum expiré ne peut pas être "rafraîchi" silencieusement
    // (il n'y a pas de refresh-token séparé) : on ferme simplement la session.
    if (error.response?.status === 401) {
      useAuthStore.getState().clearSession();
      if (!window.location.pathname.startsWith("/connexion")) {
        window.location.href = "/connexion";
      }
    }

    // Un appel en `responseType: 'blob'` (aperçu PDF, export…) reçoit son
    // erreur elle aussi en Blob plutôt qu'en JSON déjà parsé par axios : sans
    // ce détour, `data?.message` serait toujours vide et l'appelant ne
    // verrait jamais que le texte générique d'axios ("Request failed…").
    let data = error.response?.data;
    if (data instanceof Blob && data.type.includes("json")) {
      try {
        data = JSON.parse(await data.text());
      } catch {
        // Corps illisible (HTML d'erreur serveur, etc.) : on garde le blob,
        // le message générique prendra le relais plus bas.
      }
    }

    const apiError: ApiError = {
      message: data?.message ?? error.message,
      status: error.response?.status ?? 0,
      errors: data?.errors ?? null,
    };

    // 423 : mot de passe provisoire encore en place. L'API ferme tout le reste,
    // on conduit à la page de renouvellement sans alerte — ce n'est pas un
    // incident, c'est une étape d'ouverture de compte.
    if (
      error.response?.status === 423 &&
      !window.location.pathname.startsWith("/mot-de-passe")
    ) {
      window.location.href = "/mot-de-passe";
    }

    // Un refus d'autorisation se signale ici, une fois pour toute l'application :
    // l'API nomme le privilège manquant (cf. VerifierPermission), il n'y a donc
    // rien à reformuler page par page. La promesse est tout de même rejetée,
    // sans quoi l'appelant croirait son action réussie.
    if (apiError.status === 403 && !config?.silent403) {
      permissionManquante(apiError.message);
    }

    return Promise.reject(apiError);
  },
);
