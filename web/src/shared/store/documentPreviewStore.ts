import { create } from 'zustand'

interface DocumentPreviewState {
  document: Blob | null
  titre: string
  open: (document: Blob, titre?: string) => void
  close: () => void
}

/**
 * Aperçu de document en pleine page plutôt qu'un nouvel onglet : un onglet
 * PDF sort l'utilisateur du contexte de l'application (double navigation,
 * perte de l'historique React Router). Le PDF reste en memoire pour etre
 * rendu et imprime sans dependre du lecteur integre au navigateur.
 */
export const useDocumentPreviewStore = create<DocumentPreviewState>((set) => ({
  document: null,
  titre: 'Aperçu du document',
  open: (document, titre = 'Aperçu du document') => set({ document, titre }),
  close: () => set({ document: null }),
}))
