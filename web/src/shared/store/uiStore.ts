import { create } from 'zustand'
import { persist } from 'zustand/middleware'

interface UiState {
  locale: 'fr' | 'en'
  setLocale: (locale: 'fr' | 'en') => void
  sidebarOpen: boolean
  toggleSidebar: () => void
}

export const useUiStore = create<UiState>()(
  persist(
    (set, get) => ({
      locale: 'fr',
      setLocale: (locale) => set({ locale }),
      sidebarOpen: true,
      toggleSidebar: () => set({ sidebarOpen: !get().sidebarOpen }),
    }),
    { name: 'elites-school-ui' },
  ),
)
