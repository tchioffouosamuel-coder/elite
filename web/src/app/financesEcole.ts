import type { AuthUser } from '@/shared/store/authStore'

/**
 * Finances de l'établissement (caisse, doublons, tarifs, dépenses) : un
 * enseignant n'y a accès que s'il tient la caisse (`finance.encaisser`).
 * `finance.view` seul ne suffit pas — un agent également parent (compte
 * fusionné) l'hérite du rôle parent, prévu pour suivre les frais de ses
 * enfants, pas la situation financière de toute l'école.
 */
export function peutVoirFinancesEcole(user: AuthUser | null | undefined, can: (permission: string) => boolean): boolean {
  if (!can('finance.view')) return false
  if (!user?.est_enseignant) return true
  return can('finance.encaisser')
}
