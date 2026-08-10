import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import type { Departement } from '@/features/personnel/api'

export interface Matiere {
  id: number
  nom: string
  abbreviation: string | null
  statut: string
  departement: Departement | null
}

export interface MatierePayload {
  nom: string
  abbreviation?: string | null
  departement_id?: number | null
}

export interface ClasseMatiere {
  id: number
  matiere: { id: number; nom: string; abbreviation: string | null }
  enseignant: { id: number; nom_complet: string } | null
  coefficient: number
  quota_horaire: number | null
  groupe: number
  competences: string | null
  statut: string
}

export interface ClasseMatierePayload {
  matiere_id: number
  personnel_id?: number | null
  coefficient: number
  quota_horaire?: number | null
  groupe?: number
}

export interface Sequence {
  id: number
  ordre: number
  libelle: string
}

export interface Trimestre {
  id: number
  annee_scolaire_id: number
  libelle: string
  ordre: number
  is_active: boolean
  sequences: Sequence[]
}

export async function fetchMatieres(): Promise<Matiere[]> {
  const { data } = await http.get<ApiResponse<Matiere[]>>('/matieres')
  return data.data
}

export async function createMatiere(payload: MatierePayload): Promise<Matiere> {
  const { data } = await http.post<ApiResponse<Matiere>>('/matieres', payload)
  return data.data
}

export async function fetchClasseMatieres(classeId: number): Promise<ClasseMatiere[]> {
  const { data } = await http.get<ApiResponse<ClasseMatiere[]>>(`/classes/${classeId}/matieres`)
  return data.data
}

export async function affecterMatiere(classeId: number, payload: ClasseMatierePayload): Promise<ClasseMatiere> {
  const { data } = await http.post<ApiResponse<ClasseMatiere>>(`/classes/${classeId}/matieres`, payload)
  return data.data
}

export async function retirerMatiere(classeMatiereId: number): Promise<void> {
  await http.delete(`/classe-matieres/${classeMatiereId}`)
}

export async function fetchTrimestres(): Promise<Trimestre[]> {
  const { data } = await http.get<ApiResponse<Trimestre[]>>('/trimestres')
  return data.data
}
