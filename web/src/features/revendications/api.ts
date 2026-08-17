import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export type TypeRevendication = 'note' | 'decision' | 'autre'
export type StatutRevendication = 'en_attente' | 'en_cours' | 'resolue' | 'rejetee'

export interface Revendication {
  id: number
  eleve: { id: number; nom_complet: string; classe: string | null }
  matiere: string | null
  trimestre: string | null
  type: TypeRevendication
  objet: string
  motif: string
  statut: StatutRevendication
  decision: string | null
  date_reception: string
  date_traitement: string | null
  enregistre_par: string | null
  traite_par: string | null
}

export interface RevendicationPayload {
  eleve_id: number
  classe_matiere_id?: number | null
  trimestre_id?: number | null
  type: TypeRevendication
  objet: string
  motif: string
  date_reception: string
}

export async function fetchRevendications(params: {
  eleve_id?: number
  classe_id?: number
  statut?: StatutRevendication
  type?: TypeRevendication
} = {}): Promise<Revendication[]> {
  const { data } = await http.get<ApiResponse<Revendication[]>>('/revendications', { params })
  return data.data
}

export async function creerRevendication(payload: RevendicationPayload): Promise<Revendication> {
  const { data } = await http.post<ApiResponse<Revendication>>('/revendications', payload)
  return data.data
}

/** Seuls le statut et la décision motivée évoluent après coup (cf. UpdateRevendicationRequest). */
export async function traiterRevendication(
  id: number,
  payload: { statut: StatutRevendication; decision?: string | null },
): Promise<Revendication> {
  const { data } = await http.put<ApiResponse<Revendication>>(`/revendications/${id}`, payload)
  return data.data
}

export async function supprimerRevendication(id: number): Promise<void> {
  await http.delete(`/revendications/${id}`)
}
