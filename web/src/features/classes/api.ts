import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface Niveau {
  id: number
  code: string
  name_fr: string
  name_en: string
}

export interface AnneeScolaire {
  id: number
  libelle: string
  date_debut: string
  date_fin: string
  is_active: boolean
}

export interface Responsable {
  id: number
  nom_complet: string
}

export interface Classe {
  id: number
  nom: string
  filiere: string | null
  capacite: number | null
  effectif?: number
  niveau_id: number
  niveau_scolaire_id: number | null
  annee_scolaire_id: number
  niveau: { id: number; code: string; name_fr: string } | null
  /** Niveau d'enseignement (SIL, CP…) — primaire et maternelle uniquement. */
  niveau_scolaire: { id: number; code: string; libelle: string } | null
  professeur_principal_id: number | null
  titulaire_id: number | null
  titulaire: Responsable | null
  surveillant_general_id: number | null
  censeur_id: number | null
  conseiller_orientation_id: number | null
  professeur_principal: Responsable | null
  surveillant_general: Responsable | null
  censeur: Responsable | null
  conseiller_orientation: Responsable | null
}

export interface ClassePayload {
  niveau_id: number
  niveau_scolaire_id?: number | null
  annee_scolaire_id: number
  nom: string
  filiere?: string | null
  capacite?: number | null
  professeur_principal_id?: number | null
  titulaire_id?: number | null
  surveillant_general_id?: number | null
  censeur_id?: number | null
  conseiller_orientation_id?: number | null
}

export async function fetchNiveaux(): Promise<Niveau[]> {
  const { data } = await http.get<ApiResponse<Niveau[]>>('/niveaux')
  return data.data
}

export async function fetchAnneesScolaires(): Promise<AnneeScolaire[]> {
  const { data } = await http.get<ApiResponse<AnneeScolaire[]>>('/annees-scolaires')
  return data.data
}

export async function fetchClasses(anneeScolaireId?: number): Promise<Classe[]> {
  const { data } = await http.get<ApiResponse<Classe[]>>('/classes', {
    params: anneeScolaireId ? { annee_scolaire_id: anneeScolaireId } : undefined,
  })
  return data.data
}

export async function createClasse(payload: ClassePayload): Promise<Classe> {
  const { data } = await http.post<ApiResponse<Classe>>('/classes', payload)
  return data.data
}

export async function fetchClasse(id: number): Promise<Classe> {
  const { data } = await http.get<ApiResponse<Classe>>(`/classes/${id}`)
  return data.data
}

export async function updateClasse(id: number, payload: ClassePayload): Promise<Classe> {
  const { data } = await http.put<ApiResponse<Classe>>(`/classes/${id}`, payload)
  return data.data
}
