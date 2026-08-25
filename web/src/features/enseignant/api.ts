import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import type { DossierPersonnel, Personnel } from '@/features/personnel/api'
import type { ChampGain } from '@/features/finance/api'

export async function fetchMesInformations(): Promise<Personnel> {
  const { data } = await http.get<ApiResponse<Personnel>>('/enseignant/mes-informations')
  return data.data
}

export async function mettreAJourMesInformations(payload: Partial<DossierPersonnel>): Promise<Personnel> {
  const { data } = await http.put<ApiResponse<Personnel>>('/enseignant/mes-informations', payload)
  return data.data
}

export interface MaRemuneration extends Record<ChampGain, number> {
  date_effet: string
  mode: 'mensuel' | 'horaire'
  brut: number
  charges_salariales: number
  net: number
  cout_employeur: number
}

export async function fetchMaRemuneration(): Promise<MaRemuneration | null> {
  const { data } = await http.get<ApiResponse<MaRemuneration | null>>('/enseignant/remuneration')
  return data.data
}

export interface NouvelleEvaluation {
  titre: string
  type: string
  date_prevue?: string | null
  bareme: number
  competences?: string | null
  progression_item_id?: number | null
  questions: { enonce: string; bareme_question: number }[]
}

export async function ajouterEvaluation(classeMatiereId: number, payload: NouvelleEvaluation): Promise<unknown> {
  const { data } = await http.post<ApiResponse<unknown>>(`/enseignant/classe-matieres/${classeMatiereId}/evaluations`, payload)
  return data.data
}

export interface AffectationDepartement {
  classe_matiere_id: number
  classe: string
  matiere: string
  matiere_id: number
  enseignant: string | null
  personnel_id: number | null
  taux_remplissage: number | null
}

export interface MonDepartement {
  departement: { id: number; nom: string }
  matieres: { id: number; nom: string }[]
  affectations: AffectationDepartement[]
  taux_remplissage_moyen: number | null
}

export async function fetchMonDepartement(): Promise<MonDepartement> {
  const { data } = await http.get<ApiResponse<MonDepartement>>('/enseignant/mon-departement')
  return data.data
}
