import { http } from '@/shared/lib/http'
import { ouvrirDocument } from '@/shared/lib/download'
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

export type TypeSanction = 'avertissement' | 'blame' | 'corvee' | 'exclusion_temporaire' | 'exclusion_definitive' | 'autre'
export type StatutSanction = 'en_attente' | 'confirmee' | 'annulee'

export interface Sanction {
  id: number
  eleve: { id: number; nom_complet: string }
  classe: string
  type: TypeSanction
  duree_jours: number | null
  date_debut: string | null
  date_fin: string | null
  motif: string
  commentaire: string | null
  date_sanction: string
  statut: StatutSanction
  impacte_bulletin: boolean
  enregistre_par: string | null
}

export interface SanctionPayload {
  eleve_id: number
  trimestre_id: number
  type: TypeSanction
  duree_jours?: number | null
  motif: string
  commentaire?: string | null
  date_sanction: string
  impacte_bulletin?: boolean
}

export interface DossierDisciplinaire {
  total_sanctions: number
  sanctions_en_cours: number
  est_exclu: boolean
  motif_exclusion: string | null
  date_exclusion: string | null
  sanctions: Sanction[]
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

/** PV du conseil de discipline : les sanctions en attente pour ce trimestre (et cette classe si précisée). */
export function ouvrirPvConseil(trimestreId: number, classeId?: number): Promise<void> {
  return ouvrirDocument('/sanctions/pv-conseil/pdf', { trimestre_id: trimestreId, classe_id: classeId })
}

export async function updateSanction(
  id: number,
  payload: { statut?: StatutSanction; commentaire?: string | null; impacte_bulletin?: boolean },
): Promise<Sanction> {
  const { data } = await http.put<ApiResponse<Sanction>>(`/sanctions/${id}`, payload)
  return data.data
}

export async function deleteSanction(id: number): Promise<void> {
  await http.delete(`/sanctions/${id}`)
}

export async function fetchDossierDisciplinaire(eleveId: number): Promise<DossierDisciplinaire> {
  const { data } = await http.get<ApiResponse<DossierDisciplinaire>>(`/eleves/${eleveId}/sanctions`)
  return data.data
}
