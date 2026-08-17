import { http } from '@/shared/lib/http'
import type { ApiResponse, Pagination } from '@/shared/types/api'

export interface Annonce {
  id: number
  titre: string
  contenu: string
  publiee_le: string
  publie_par: { id: number; nom_complet: string } | null
}

export async function fetchAnnonces(page = 1): Promise<{ items: Annonce[]; pagination: Pagination }> {
  const { data } = await http.get<ApiResponse<Annonce[]>>('/annonces', { params: { page } })
  return { items: data.data, pagination: data.meta!.pagination! }
}

export async function creerAnnonce(payload: { titre: string; contenu: string }): Promise<Annonce> {
  const { data } = await http.post<ApiResponse<Annonce>>('/annonces', payload)
  return data.data
}

export async function supprimerAnnonce(id: number): Promise<void> {
  await http.delete(`/annonces/${id}`)
}
