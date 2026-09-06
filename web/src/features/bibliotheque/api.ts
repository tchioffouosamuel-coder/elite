import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface DocumentBibliotheque {
  id: number
  titre: string
  description: string | null
  fichier_url: string
  fichier_nom_original: string
  taille: number
  type_mime: string | null
  ecoles: { id: number; name: string }[]
  uploade_par: string | null
  created_at: string
}

/** Vue en lecture seule (espaces personnel/parent) : ni la liste des écoles, ni le déposant. */
export type DocumentBibliothequeLecture = Omit<DocumentBibliotheque, 'ecoles' | 'uploade_par'>

export async function fetchBibliotheque(): Promise<DocumentBibliotheque[]> {
  const { data } = await http.get<ApiResponse<DocumentBibliotheque[]>>('/bibliotheque')
  return data.data
}

/** `FormData` et non JSON : le document porte un fichier. */
export async function uploaderDocument(champs: {
  titre: string
  description?: string
  fichier: File
  school_ids: number[]
}): Promise<DocumentBibliotheque> {
  const formulaire = new FormData()
  formulaire.append('titre', champs.titre)
  if (champs.description) formulaire.append('description', champs.description)
  formulaire.append('fichier', champs.fichier)
  champs.school_ids.forEach((id) => formulaire.append('school_ids[]', String(id)))

  const { data } = await http.post<ApiResponse<DocumentBibliotheque>>('/bibliotheque', formulaire, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function supprimerDocument(id: number): Promise<void> {
  await http.delete(`/bibliotheque/${id}`)
}
