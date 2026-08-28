import { create } from 'zustand'

interface DocumentPreviewState {
  url: string | null
  titre: string
  open: (url: string, titre?: string) => void
  close: () => void
}

/**
 * Aperçu de document en pleine page plutôt qu'un nouvel onglet : un onglet
 * PDF sort l'utilisateur du contexte de l'application (double navigation,
 * perte de l'historique React Router). Le blob URL est révoqué à la
 * fermeture ou au remplacement pour ne pas fuiter de mémoire.
 */
export const useDocumentPreviewStore = create<DocumentPreviewState>((set, get) => ({
  url: null,
  titre: 'Aperçu du document',
  open: (url, titre = 'Aperçu du document') => {
    const precedent = get().url
    if (precedent) URL.revokeObjectURL(precedent)
    set({ url, titre })
  },
  close: () => {
    const precedent = get().url
    if (precedent) URL.revokeObjectURL(precedent)
    set({ url: null })
  },
}))
