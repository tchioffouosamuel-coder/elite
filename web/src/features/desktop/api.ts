import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface StatutSyncEcole {
  school_id: number
  nom: string | null
  dernier_pull_le: string | null
  /** `null` = non vérifié (pas demandé, ou aléa réseau) ; `true`/`false` = vérifié. */
  complet: boolean | null
}

export interface StatutSync {
  dernier_pull_le: string | null
  dernier_push_le: string | null
  en_attente_push: number
  ecoles: StatutSyncEcole[]
}

/** `verifier` déclenche une comparaison avec le serveur distant (plus lent, un appel réseau par école) — à ne demander qu'à l'ouverture du panneau, pas à chaque sondage. */
export async function fetchStatutSync(verifier = false): Promise<StatutSync> {
  const { data } = await http.get<ApiResponse<StatutSync>>('/desktop/statut-sync', {
    params: verifier ? { verifier: 1 } : undefined,
  })
  return data.data
}

export interface ResultatSynchronisation {
  pull: { code: number; message: string }
  push: { code: number; message: string }
}

/** Requête bloquante côté serveur (pull puis push synchrones) : peut prendre plusieurs secondes, l'appelant doit afficher un état de chargement. */
export async function lancerSynchronisation(): Promise<ResultatSynchronisation> {
  const { data } = await http.post<ApiResponse<ResultatSynchronisation>>('/desktop/synchroniser')
  return data.data
}
