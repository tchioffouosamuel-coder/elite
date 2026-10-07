import type { AuthUser } from '@/shared/store/authStore'

export function peutSaisirSequence(
  user: AuthUser | null,
  sequence: { saisie_ouverte?: boolean },
  trimestreActif: boolean,
): boolean {
  if (!user) return false
  const direction = user.is_super_admin || user.roles.some((r) =>
    ['super_admin', 'admin_ecole', 'admin_college', 'censeur_sg'].includes(r))
  return (direction || trimestreActif) &&
    ((!user.est_enseignant && direction) || sequence.saisie_ouverte === true)
}
