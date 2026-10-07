import { estPersonnelDirection } from '@/shared/lib/direction'
import type { AuthUser } from '@/shared/store/authStore'

export function peutSaisirSequence(
  user: AuthUser | null,
  sequence: { saisie_ouverte?: boolean },
  trimestreActif: boolean,
): boolean {
  if (!user) return false
  const direction = estPersonnelDirection(user)
  return (direction || trimestreActif) &&
    ((!user.est_enseignant && direction) || sequence.saisie_ouverte === true)
}
