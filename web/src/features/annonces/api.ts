import { http } from '@/shared/lib/http'
import type { ApiResponse, Pagination } from '@/shared/types/api'

export interface Annonce {
  id: number
  titre: string
  contenu: string
  publiee_le: string
  publie_par: { id: number; nom_complet: string } | null
  school_id?: number
  school?: { id: number; name: string; code: string; type: string } | null
}

export type CibleType = 'tous' | 'fonction' | 'utilisateurs'

export interface Fonction {
  id: number
  label: string
}

export interface Destinataire {
  id: number
  nom_complet: string
}

export async function fetchAnnonces(page = 1): Promise<{ items: Annonce[]; pagination: Pagination }> {
  const { data } = await http.get<ApiResponse<Annonce[]>>('/annonces', { params: { page } })
  return { items: data.data, pagination: data.meta!.pagination! }
}

export async function creerAnnonce(payload: {
  titre: string
  contenu: string
  school_id?: number | null
  cible_type?: CibleType
  cible?: number[]
}): Promise<Annonce> {
  const { data } = await http.post<ApiResponse<Annonce>>('/annonces', payload)
  return data.data
}

export async function supprimerAnnonce(id: number): Promise<void> {
  await http.delete(`/annonces/${id}`)
}

/** Fonctions du référentiel de l'école, pour le ciblage « par fonction ». */
export async function fetchFonctionsAnnonces(schoolId?: number | null): Promise<Fonction[]> {
  const { data } = await http.get<ApiResponse<Fonction[]>>('/annonces/fonctions', {
    params: schoolId ? { school_id: schoolId } : undefined,
  })
  return data.data
}

/** Recherche de destinataires par nom, pour le ciblage « utilisateurs ». */
export async function rechercherDestinatairesAnnonces(recherche: string, schoolId?: number | null): Promise<Destinataire[]> {
  const { data } = await http.get<ApiResponse<Destinataire[]>>('/annonces/destinataires', {
    params: { recherche, ...(schoolId ? { school_id: schoolId } : {}) },
  })
  return data.data
}
