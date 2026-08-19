import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface VisiteInfirmerie {
  id: number
  eleve: { id: number; nom_complet: string }
  classe: { id: number; nom: string } | null
  school?: { id: number; name: string; code: string; type: string } | null
  date_visite: string
  raison: string
  soins_prodiges: string
  cout_soins: number
  observations: string | null
  enregistre_par: string | null
  created_at: string | null
  updated_at: string | null
}

export interface VisiteInfirmeriePayload {
  eleve_id: number
  date_visite: string
  raison: string
  soins_prodiges: string
  cout_soins?: number | null
  observations?: string | null
}

export interface VisitesInfirmerieParams {
  eleve_id?: number
  classe_id?: number
  du?: string
  au?: string
}

export async function fetchVisitesInfirmerie(params: VisitesInfirmerieParams = {}): Promise<VisiteInfirmerie[]> {
  const { data } = await http.get<ApiResponse<VisiteInfirmerie[]>>('/infirmerie/visites', { params })
  return data.data
}

export async function createVisiteInfirmerie(payload: VisiteInfirmeriePayload): Promise<VisiteInfirmerie> {
  const { data } = await http.post<ApiResponse<VisiteInfirmerie>>('/infirmerie/visites', payload)
  return data.data
}

export async function updateVisiteInfirmerie(id: number, payload: VisiteInfirmeriePayload): Promise<VisiteInfirmerie> {
  const { data } = await http.put<ApiResponse<VisiteInfirmerie>>(`/infirmerie/visites/${id}`, payload)
  return data.data
}

export async function deleteVisiteInfirmerie(id: number): Promise<void> {
  await http.delete(`/infirmerie/visites/${id}`)
}
