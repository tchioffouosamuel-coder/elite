import { http } from '@/shared/lib/http'
import type { ApiResponse, Pagination } from '@/shared/types/api'

export interface Tuteur {
  id: number
  nom_complet: string
  telephone: string | null
  email: string | null
  lien_parente: string | null
  is_principal: boolean
}

export interface Eleve {
  id: number
  matricule: string | null
  nom: string
  prenom: string
  nom_complet: string
  sexe: 'M' | 'F'
  date_naissance: string | null
  redoublant: boolean
  statut: string
  classe: { id: number; nom: string; niveau: string | null } | null
  tuteurs: Tuteur[]
}

export interface EleveTuteurInput {
  nom: string
  prenom: string
  telephone?: string
  lien_parente?: string
  is_principal?: boolean
}

export interface ElevePayload {
  classe_id?: number | null
  nom: string
  prenom: string
  sexe: 'M' | 'F'
  date_naissance?: string | null
  tuteurs?: EleveTuteurInput[]
}

export async function fetchEleves(params: { search?: string; classe_id?: number; page?: number; per_page?: number }): Promise<{
  items: Eleve[]
  pagination: Pagination
}> {
  const { data } = await http.get<ApiResponse<Eleve[]>>('/eleves', { params })
  return { items: data.data, pagination: data.meta!.pagination! }
}

export async function createEleve(payload: ElevePayload): Promise<Eleve> {
  const { data } = await http.post<ApiResponse<Eleve>>('/eleves', payload)
  return data.data
}
