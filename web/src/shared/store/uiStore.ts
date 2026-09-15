import { create } from 'zustand'
import { persist } from 'zustand/middleware'

interface UiState {
  locale: 'fr' | 'en'
  setLocale: (locale: 'fr' | 'en') => void
  sidebarOpen: boolean
  toggleSidebar: () => void
  /**
   * Montants financiers masqués (Caisse, État de synthèse, Dettes
   * antérieures, Insolvables) — un seul réglage partagé par ces écrans,
   * pour qu'activer/désactiver la discrétion une fois vaille partout.
   * Masqué par défaut : les montants sont sensibles, mieux vaut un geste
   * explicite pour les révéler qu'un oubli qui les affiche à l'écran.
   */
  montantsMasques: boolean
  toggleMontantsMasques: () => void
}

export const useUiStore = create<UiState>()(
  persist(
    (set, get) => ({
      locale: 'fr',
      setLocale: (locale) => set({ locale }),
      sidebarOpen: true,
      toggleSidebar: () => set({ sidebarOpen: !get().sidebarOpen }),
      montantsMasques: true,
      toggleMontantsMasques: () => set({ montantsMasques: !get().montantsMasques }),
    }),
    { name: 'elites-school-ui' },
  ),
)
