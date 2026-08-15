import { http } from '@/shared/lib/http'
import type { ApiResponse, Pagination } from '@/shared/types/api'

export interface Departement {
  id: number
  nom: string
}

export interface Personnel {
  id: number
  matricule: string | null
  nom_complet: string
  fonction_id: number
  fonction: string
  departement: Departement | null
  telephone: string | null
  email: string | null
  statut: 'actif' | 'ex_employe'
  a_un_compte: boolean
}

export interface PersonnelPayload {
  nom_complet: string
  fonction_id: number
  departement_id?: number | null
  matricule?: string | null
  telephone?: string | null
  email?: string | null
}

export interface FonctionReferentiel {
  id: number
  school_id: number
  label_fr: string
  label_en: string | null
  label: string
  personnels_count?: number
}

export async function fetchDepartements(): Promise<Departement[]> {
  const { data } = await http.get<ApiResponse<Departement[]>>('/departements')
  return data.data
}

export async function createDepartement(nom: string): Promise<Departement> {
  const { data } = await http.post<ApiResponse<Departement>>('/departements', { nom })
  return data.data
}

export async function fetchFonctionsReferentiel(): Promise<FonctionReferentiel[]> {
  const { data } = await http.get<ApiResponse<FonctionReferentiel[]>>('/fonctions-referentiel')
  return data.data
}

export async function fetchFonctionReferentiel(id: number): Promise<FonctionReferentiel> {
  const { data } = await http.get<ApiResponse<FonctionReferentiel>>(`/fonctions-referentiel/${id}`)
  return data.data
}

export async function createFonctionReferentiel(payload: {
  label_fr: string
  label_en?: string | null
}): Promise<FonctionReferentiel> {
  const { data } = await http.post<ApiResponse<FonctionReferentiel>>('/fonctions-referentiel', payload)
  return data.data
}

export async function updateFonctionReferentiel(
  id: number,
  payload: {
    label_fr: string
    label_en?: string | null
  },
): Promise<FonctionReferentiel> {
  const { data } = await http.put<ApiResponse<FonctionReferentiel>>(`/fonctions-referentiel/${id}`, payload)
  return data.data
}

export async function deleteFonctionReferentiel(id: number): Promise<void> {
  await http.delete(`/fonctions-referentiel/${id}`)
}

export async function batchDeleteFonctionsReferentiel(ids: number[]): Promise<{ deleted: number; ignorees: string[] }> {
  const { data } = await http.post<ApiResponse<{ deleted: number; ignorees: string[] }>>('/fonctions-referentiel/batch-delete', { ids })
  return data.data
}

export async function fetchPersonnels(params: {
  search?: string
  departement_id?: number
  fonction_id?: number
  statut?: string
  page?: number
  per_page?: number
}): Promise<{ items: Personnel[]; pagination: Pagination }> {
  const { data } = await http.get<ApiResponse<Personnel[]>>('/personnels', { params })
  return { items: data.data, pagination: data.meta!.pagination! }
}

export async function createPersonnel(payload: PersonnelPayload): Promise<Personnel> {
  const { data } = await http.post<ApiResponse<Personnel>>('/personnels', payload)
  return data.data
}

export async function updatePersonnel(id: number, payload: PersonnelPayload): Promise<Personnel> {
  const { data } = await http.put<ApiResponse<Personnel>>(`/personnels/${id}`, payload)
  return data.data
}

export async function archivePersonnel(id: number): Promise<void> {
  await http.post(`/personnels/${id}/archive`)
}

export async function reactivatePersonnel(id: number): Promise<void> {
  await http.post(`/personnels/${id}/reactivate`)
}

export async function deletePersonnel(id: number): Promise<void> {
  await http.delete(`/personnels/${id}`)
}

export async function batchDeletePersonnel(ids: number[]): Promise<{ deleted: number }> {
  const { data } = await http.post<ApiResponse<{ deleted: number }>>('/personnels/batch-delete', { ids })
  return data.data
}

export async function batchArchivePersonnel(ids: number[]): Promise<void> {
  await Promise.all(ids.map((id) => archivePersonnel(id)))
}

export async function createLoginAccount(id: number, email: string, role: string): Promise<void> {
  await http.post(`/personnels/${id}/compte`, { email, role })
}
