import { useAuthStore } from '@/shared/store/authStore'

export type TypeEcole = 'maternelle' | 'primaire' | 'secondaire'

/**
 * Type de l'établissement sur lequel l'interface travaille. Lu hors composant
 * (les fonctions d'API ne sont pas des hooks) afin que les appels aiguillent
 * d'eux-mêmes vers le bon endpoint, plutôt que d'obliger chaque page à
 * transmettre le type — une page qui l'oublierait interrogerait silencieusement
 * le mauvais moteur de notation.
 */
export function typeEcoleActive(): TypeEcole {
  return useAuthStore.getState().activeSchool()?.type ?? 'secondaire'
}

/** Le secondaire note par séquence ; le primaire et la maternelle par volets. */
export function estSecondaire(): boolean {
  return typeEcoleActive() === 'secondaire'
}
