import { http } from '@/shared/lib/http'
import type { ApiResponse, Pagination } from '@/shared/types/api'

export interface Departement {
  id: number
  nom: string
}

export interface Personnel {
  id: number
  matricule: string | null
  nom: string
  prenom: string
  nom_complet: string
  fonction: string
  departement: Departement | null
  telephone: string | null
  email: string | null
  statut: 'actif' | 'ex_employe'
  a_un_compte: boolean
}

export interface PersonnelPayload {
  nom: string
  prenom: string
  fonction: string
  departement_id?: number | null
  matricule?: string | null
  telephone?: string | null
  email?: string | null
}

export async function fetchDepartements(): Promise<Departement[]> {
  const { data } = await http.get<ApiResponse<Departement[]>>('/departements')
  return data.data
}

export async function createDepartement(nom: string): Promise<Departement> {
  const { data } = await http.post<ApiResponse<Departement>>('/departements', { nom })
  return data.data
}

export async function fetchPersonnels(params: { search?: string; page?: number; per_page?: number }): Promise<{ items: Personnel[]; pagination: Pagination }> {
  const { data } = await http.get<ApiResponse<Personnel[]>>('/personnels', { params })
  return { items: data.data, pagination: data.meta!.pagination! }
}

export async function createPersonnel(payload: PersonnelPayload): Promise<Personnel> {
  const { data } = await http.post<ApiResponse<Personnel>>('/personnels', payload)
  return data.data
}

export async function archivePersonnel(id: number): Promise<void> {
  await http.post(`/personnels/${id}/archive`)
}

export async function reactivatePersonnel(id: number): Promise<void> {
  await http.post(`/personnels/${id}/reactivate`)
}

export async function createLoginAccount(id: number, email: string, role: string): Promise<void> {
  await http.post(`/personnels/${id}/compte`, { email, role })
}
