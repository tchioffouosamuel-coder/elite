import axios from "axios";
import type { AxiosError } from "axios";
import { useAuthStore } from "@/shared/store/authStore";
import { useUiStore } from "@/shared/store/uiStore";
import { permissionManquante } from "@/shared/lib/alertes";
import type { ApiError } from "@/shared/types/api";

export const API_BASE_URL =
  import.meta.env.VITE_API_URL ?? "http://127.0.0.1:8000/api/v1";

export const http = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30 * 60 * 1000,
  headers: { Accept: "application/json" },
});

type DesktopRequest = {
  method: string;
  url: string;
  data?: unknown;
  headers?: Record<string, string>;
};

type DesktopBridge = {
  cacheGet: (key: string) => Promise<unknown | null>;
  cachePut: (key: string, value: unknown) => Promise<void>;
  enqueue: (request: DesktopRequest) => Promise<void>;
  sync: (options: {
    baseUrl: string;
    token: string;
    schoolId?: number | null;
    locale: string;
  }) => Promise<number>;
  bootstrap: (options: {
    baseUrl: string;
    token: string;
    schoolId?: number | null;
    locale: string;
  }) => Promise<{ passes: number; entities: number }>;
};

function desktopBridge(): DesktopBridge | null {
  return window.desktop ?? null;
}

function cacheKey(config: { url?: string; params?: unknown }): string {
  const params = config.params ? JSON.stringify(config.params) : "";
  return `${config.url ?? ""}?${params}`;
}

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

  if (token) {
    void desktopBridge()?.sync({
      baseUrl: API_BASE_URL,
      token,
      schoolId: activeSchoolId,
      locale,
    });
  }

  return config;
});

http.interceptors.response.use(
  async (response) => {
    const bridge = desktopBridge();
    if (bridge && response.config.method?.toLowerCase() === "get") {
      await bridge.cachePut(cacheKey(response.config), response.data);
    }
    return response;
  },
  async (
    error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>,
  ) => {
    const bridge = desktopBridge();
    const config = error.config;
    const method = config?.method?.toLowerCase() ?? "get";
    const url = config?.url ?? "";

    if (bridge && config && !error.response) {
      if (method === "get") {
        const cached = await bridge.cacheGet(cacheKey(config));
        if (cached !== null) {
          return {
            ...error,
            config,
            data: cached,
            status: 200,
            statusText: "OK",
            headers: {},
            request: undefined,
          };
        }
      } else if (config.data) {
        await bridge.enqueue({
          method,
          url,
          data: config.data as unknown,
          headers: config.headers as Record<string, string>,
        });
      }
    }

    // Un jeton Sanctum expiré ne peut pas être "rafraîchi" silencieusement
    // (il n'y a pas de refresh-token séparé) : on ferme simplement la session.
    if (error.response?.status === 401) {
      useAuthStore.getState().clearSession();
      if (!window.location.pathname.startsWith("/connexion")) {
        window.location.href = "/connexion";
      }
    }

    const apiError: ApiError = {
      message: error.response?.data?.message ?? error.message,
      status: error.response?.status ?? 0,
      errors: error.response?.data?.errors ?? null,
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
    if (apiError.status === 403) {
      permissionManquante(apiError.message);
    }

    return Promise.reject(apiError);
  },
);
