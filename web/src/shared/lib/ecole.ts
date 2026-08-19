import { useAuthStore } from '@/shared/store/authStore'

export type TypeEcole = 'maternelle' | 'primaire' | 'secondaire'

/**
 * Type de l'établissement sur lequel l'interface travaille. Lu hors composant
 * (les fonctions d'API ne sont pas des hooks) afin que les appels aiguillent
 * d'eux-mêmes vers le bon endpoint, plutôt que d'obliger chaque page à
 * transmettre le type — une page qui l'oublierait interrogerait silencieusement
 * le mauvais moteur de notation.
 *
 * `ecole` : type explicite d'un enregistrement précis (classe, élève…), à
 * fournir dès qu'on en tient un — en mode agrégé (super admin, "Toutes les
 * écoles"), il n'y a pas d'école active unique, et retomber sur 'secondaire'
 * par défaut serait faux pour une donnée du primaire ou de la maternelle.
 */
export function typeEcoleActive(ecole?: TypeEcole | null): TypeEcole {
  return ecole ?? useAuthStore.getState().activeSchool()?.type ?? 'secondaire'
}

/** Le secondaire note par séquence ; le primaire et la maternelle par volets. */
export function estSecondaire(ecole?: TypeEcole | null): boolean {
  return typeEcoleActive(ecole) === 'secondaire'
}

/**
 * Seul le primaire s'organise en degrés d'enseignement (SIL, CP, CE1…) placés
 * sous un animateur de niveau. La maternelle ne connaît pas cette notion : ses
 * classes — petite, moyenne et grande section — se suffisent à elles-mêmes.
 */
export function utiliseNiveaux(ecole?: TypeEcole | null): boolean {
  return typeEcoleActive(ecole) === 'primaire'
}
