import type { AuthUser } from '@/shared/store/authStore'

/**
 * Le compte relève-t-il de la direction ?
 *
 * Même liste que `User::estPersonnelDirection()` côté API, qui s'en sert pour
 * dispenser ces comptes de la preuve de présence (ils remplissent l'appel à
 * distance, pour suivre ou corriger) et pour les autoriser à écrire en dehors
 * du jour courant. Le serveur reste l'autorité : ceci ne sert qu'à ne pas
 * proposer à l'écran ce qu'il refusera.
 */
export function estPersonnelDirection(user: AuthUser | null): boolean {
  if (!user) return false

  return user.is_super_admin
    || user.roles.some((r) => ['super_admin', 'admin_ecole', 'admin_college', 'censeur_sg'].includes(r))
}
