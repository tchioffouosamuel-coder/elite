import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

/** Le secondaire compte des heures saisies à la main, le primaire et la
 *  maternelle des journées déduites des appels (`calculee`, donc non éditable). */
export type UniteAbsence = 'heures' | 'jours'

export interface AbsenceCellule {
  eleve_id: number
  nom_complet: string
  unite: UniteAbsence
  justifiees: number
  non_justifiees: number
  calculee: boolean
}

export interface BilanDisciplinaire {
  effectif: number
  unite: UniteAbsence
  total_hnj: number
  moyenne_hnj: number
  total_hnj_garcons: number
  moyenne_hnj_garcons: number
  total_hnj_filles: number
  moyenne_hnj_filles: number
  eleve_plus_absent: { nom_complet: string; heures_non_justifiees: number } | null
}

export interface Sanction {
  id: number
  eleve: { id: number; nom_complet: string }
  classe: string
  type: 'corvee' | 'exclusion_temporaire' | 'exclusion_definitive' | 'autre'
  duree_jours: number | null
  motif: string
  date_sanction: string
  enregistre_par: string | null
}

export interface SanctionPayload {
  eleve_id: number
  trimestre_id: number
  type: Sanction['type']
  duree_jours?: number | null
  motif: string
  date_sanction: string
}

export async function fetchAbsences(classeId: number, trimestreId: number): Promise<AbsenceCellule[]> {
  const { data } = await http.get<ApiResponse<AbsenceCellule[]>>(`/classes/${classeId}/absences`, {
    params: { trimestre_id: trimestreId },
  })
  return data.data
}

export async function sauvegarderAbsences(
  classeId: number,
  trimestreId: number,
  absences: { eleve_id: number; heures_justifiees: number; heures_non_justifiees: number }[],
): Promise<{ saved: number }> {
  const { data } = await http.post<ApiResponse<{ saved: number }>>(`/classes/${classeId}/absences`, {
    trimestre_id: trimestreId,
    absences,
  })
  return data.data
}

export async function fetchBilanDisciplinaire(classeId: number, trimestreId: number): Promise<BilanDisciplinaire> {
  const { data } = await http.get<ApiResponse<BilanDisciplinaire>>(`/classes/${classeId}/bilan-disciplinaire`, {
    params: { trimestre_id: trimestreId },
  })
  return data.data
}

export async function fetchSanctions(params: { classe_id?: number; eleve_id?: number }): Promise<Sanction[]> {
  const { data } = await http.get<ApiResponse<Sanction[]>>('/sanctions', { params })
  return data.data
}

export async function createSanction(payload: SanctionPayload): Promise<Sanction> {
  const { data } = await http.post<ApiResponse<Sanction>>('/sanctions', payload)
  return data.data
}

export async function deleteSanction(id: number): Promise<void> {
  await http.delete(`/sanctions/${id}`)
}
