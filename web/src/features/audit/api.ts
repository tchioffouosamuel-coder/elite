import { http } from "@/shared/lib/http";
import type { ApiResponse, Pagination } from "@/shared/types/api";

export type ActionAudit =
  | "connexion"
  | "connexion_echouee"
  | "deconnexion"
  | "consultation"
  | "creation"
  | "modification"
  | "suppression"
  | "import"
  | "export"
  | "impression"
  | "synchronisation"
  | "action";

export interface EntreeAudit {
  id: number;
  created_at: string;
  school_id: number | null;
  user_id: number | null;
  user_nom: string | null;
  user_role: string | null;
  action: ActionAudit;
  action_libelle: string;
  module: string | null;
  route: string | null;
  methode: string;
  url: string;
  parametres: Record<string, unknown> | null;
  statut_http: number;
  duree_ms: number | null;
  ip_address: string | null;
  nb_changements: number;
}

export interface ChangementAudit {
  operation: "created" | "updated" | "deleted";
  modele: string;
  id: number | string | null;
  avant?: Record<string, unknown>;
  apres?: Record<string, unknown>;
}

export interface DetailAudit extends EntreeAudit {
  school: string | null;
  user_agent: string | null;
  donnees: Record<string, unknown> | null;
  changements: ChangementAudit[] | null;
}

export interface FiltresAudit {
  du?: string;
  au?: string;
  user_id?: string;
  action?: string;
  module?: string;
  exclure_notifications?: string;
  methode?: string;
  statut?: string;
  recherche?: string;
}

export interface StatsAudit {
  total: number;
  par_action: Partial<Record<ActionAudit, number>>;
  erreurs: number;
  utilisateurs_distincts: number;
  top_utilisateurs: { user_id: number; user_nom: string; total: number }[];
  top_modules: { module: string; total: number }[];
}

export interface OptionsFiltresAudit {
  actions: { code: ActionAudit; libelle: string }[];
  modules: string[];
  utilisateurs: { user_id: number; user_nom: string; user_role: string | null }[];
}

/** Retire les filtres vides, et signale un rafraîchissement automatique (non journalisé côté serveur). */
function parametres(filtres: FiltresAudit, auto: boolean): Record<string, string | number> {
  const p: Record<string, string | number> = {};
  for (const [cle, valeur] of Object.entries(filtres)) {
    if (valeur) p[cle] = valeur;
  }
  if (auto) p.actualisation_auto = 1;
  return p;
}

export async function fetchJournalAudit(
  filtres: FiltresAudit,
  page: number,
  auto: boolean,
): Promise<{ items: EntreeAudit[]; pagination: Pagination }> {
  const { data } = await http.get<ApiResponse<EntreeAudit[]>>("/audit", {
    params: { ...parametres(filtres, auto), page, per_page: 50 },
  });
  return { items: data.data, pagination: data.meta!.pagination! };
}

export async function fetchStatsAudit(filtres: FiltresAudit, auto: boolean): Promise<StatsAudit> {
  const { data } = await http.get<ApiResponse<StatsAudit>>("/audit/stats", {
    params: parametres(filtres, auto),
  });
  return data.data;
}

export async function fetchOptionsFiltresAudit(): Promise<OptionsFiltresAudit> {
  const { data } = await http.get<ApiResponse<OptionsFiltresAudit>>("/audit/filtres");
  return data.data;
}

export async function fetchDetailAudit(id: number): Promise<DetailAudit> {
  const { data } = await http.get<ApiResponse<DetailAudit>>(`/audit/${id}`);
  return data.data;
}

export function parametresExportAudit(filtres: FiltresAudit): Record<string, string> {
  return parametres(filtres, false) as Record<string, string>;
}
