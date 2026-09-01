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
 * perte de l'historique React Router). `url` est une data URI (cf.
 * `ouvrirDocument`) : contrairement à un blob URL, elle n'a rien à révoquer.
 */
export const useDocumentPreviewStore = create<DocumentPreviewState>((set) => ({
  url: null,
  titre: 'Aperçu du document',
  open: (url, titre = 'Aperçu du document') => set({ url, titre }),
  close: () => set({ url: null }),
}))
