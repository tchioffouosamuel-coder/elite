import { http } from '@/shared/lib/http'
import type { ApiResponse, Pagination } from '@/shared/types/api'

export interface Avis {
  id: number
  sujet: string | null
  contenu: string
  note: number | null
  created_at: string
  auteur?: { id: number; name: string; role: string | null } | null
}

export interface AvisPayload {
  sujet?: string
  contenu: string
  note?: number
}

export interface AvisFiltres {
  role?: string
  date_debut?: string
  date_fin?: string
  note_min?: number
  page?: number
}

export async function soumettreAvis(payload: AvisPayload): Promise<Avis> {
  const { data } = await http.post<ApiResponse<Avis>>('/avis', payload)
  return data.data
}

export async function fetchMesAvis(): Promise<Avis[]> {
  const { data } = await http.get<ApiResponse<Avis[]>>('/mes-avis')
  return data.data
}

export async function fetchAvis(filtres: AvisFiltres = {}): Promise<{ avis: Avis[]; pagination?: Pagination }> {
  const { data } = await http.get<ApiResponse<Avis[]>>('/avis', { params: filtres })
  return { avis: data.data, pagination: data.meta?.pagination }
}
