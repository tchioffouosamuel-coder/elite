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
  /** Faux tant que le premier clonage complet de ce compte n'a jamais réussi sans erreur — cf. `DesktopClonageGate`. */
  clonage_initial_complet: boolean
}

/** `verifier` déclenche une comparaison avec le serveur distant (plus lent, un appel réseau par école) — à ne demander qu'à l'ouverture du panneau, pas à chaque sondage. */
export async function fetchStatutSync(verifier = false): Promise<StatutSync> {
  const { data } = await http.get<ApiResponse<StatutSync>>('/desktop/statut-sync', {
    params: verifier ? { verifier: 1 } : undefined,
  })
  return data.data
}

/**
 * Lance `sync:pull`/`sync:push` dans un process CLI séparé (cf.
 * `desktop:sync-now` dans `main.cjs`), jamais via une requête HTTP vers le
 * serveur PHP intégré : celui-ci exécutait auparavant les deux commandes en
 * ligne dans la requête (`POST /desktop/synchroniser`), et heurtait sa
 * limite `max_execution_time` sur un établissement volumineux — observé en
 * conditions réelles : « Maximum execution time of 30 seconds exceeded »
 * sur plusieurs milliers d'élèves répartis sur plusieurs écoles.
 *
 * Ne renvoie qu'un booléen (lancée ou non — `false` si une synchronisation
 * tournait déjà) : contrairement à l'ancienne route REST, on ne connaît pas
 * ici le détail pull/push, seulement que le cycle a eu lieu.
 */
export async function lancerSynchronisation(): Promise<boolean> {
  return (await window.desktop?.syncNow()) ?? false
}
