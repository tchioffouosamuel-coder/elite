import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import type { Trimestre } from '@/features/pedagogie/api'

export interface AnneeScolaire {
  id: number
  libelle: string
  date_debut: string
  date_fin: string
  is_active: boolean
}

export interface AnneeScolairePayload {
  libelle: string
  date_debut: string
  date_fin: string
  is_active?: boolean
}

export interface TrimestrePayload {
  annee_scolaire_id: number
  libelle: string
  ordre: number
  date_debut: string
  date_fin: string
}

export async function fetchAnneesScolaires(): Promise<AnneeScolaire[]> {
  const { data } = await http.get<ApiResponse<AnneeScolaire[]>>('/annees-scolaires')
  return data.data
}

export async function createAnneeScolaire(payload: AnneeScolairePayload): Promise<AnneeScolaire> {
  const { data } = await http.post<ApiResponse<AnneeScolaire>>('/annees-scolaires', payload)
  return data.data
}

export async function updateAnneeScolaire(id: number, payload: AnneeScolairePayload): Promise<AnneeScolaire> {
  const { data } = await http.put<ApiResponse<AnneeScolaire>>(`/annees-scolaires/${id}`, payload)
  return data.data
}

export async function activerAnneeScolaire(id: number): Promise<AnneeScolaire> {
  const { data } = await http.post<ApiResponse<AnneeScolaire>>(`/annees-scolaires/${id}/activer`)
  return data.data
}

export interface ResultatGenerationSeances {
  creees: number
  classes: number
}

export async function genererSeancesAnnee(id: number): Promise<ResultatGenerationSeances> {
  const { data } = await http.post<ApiResponse<ResultatGenerationSeances>>(`/annees-scolaires/${id}/generer-seances`)
  return data.data
}

export async function fetchTrimestresAll(): Promise<Trimestre[]> {
  const { data } = await http.get<ApiResponse<Trimestre[]>>('/trimestres')
  return data.data
}

export async function createTrimestre(payload: TrimestrePayload): Promise<Trimestre> {
  const { data } = await http.post<ApiResponse<Trimestre>>('/trimestres', payload)
  return data.data
}

export async function updateTrimestre(id: number, payload: Omit<TrimestrePayload, 'annee_scolaire_id'>): Promise<Trimestre> {
  const { data } = await http.put<ApiResponse<Trimestre>>(`/trimestres/${id}`, payload)
  return data.data
}

export async function activerTrimestre(id: number): Promise<Trimestre> {
  const { data } = await http.post<ApiResponse<Trimestre>>(`/trimestres/${id}/activer`)
  return data.data
}

export async function genererSeancesTrimestre(id: number): Promise<ResultatGenerationSeances> {
  const { data } = await http.post<ApiResponse<ResultatGenerationSeances>>(`/trimestres/${id}/generer-seances`)
  return data.data
}
