import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface AnneeArchivee {
  id: number
  libelle: string
  date_debut: string
  date_fin: string
  archivee_le: string
}

export interface ClasseArchivee {
  id: number
  classe_id: number | null
  classe_nom: string
  niveau_libelle: string | null
  effectif: number
}

export interface RosterLigne {
  eleve_id: number
  matricule: string | null
  nom_complet: string
  sexe: 'M' | 'F'
  decision: 'admis' | 'redouble' | 'exclu' | 'diplome' | null
  gracie: boolean
  moyenne_annuelle: number | null
  motif: string | null
}

export interface AbsenceArchivee {
  date: string | null
  statut: 'absent' | 'retard'
  motif: string | null
  justifie: boolean
  remarque: string | null
}

export interface SanctionArchivee {
  type: string
  motif: string
  commentaire: string | null
  date_sanction: string | null
  statut: string
  impacte_bulletin: boolean
}

export interface VisiteInfirmerieArchivee {
  date_visite: string | null
  raison: string | null
  soins_prodiges: string | null
  observations: string | null
}

export interface DetailArchiveClasse {
  id: number
  classe_nom: string
  niveau_libelle: string | null
  effectif: number
  roster: RosterLigne[]
  absences: Record<string, AbsenceArchivee[]>
  discipline: Record<string, SanctionArchivee[]>
  infirmerie: Record<string, VisiteInfirmerieArchivee[]>
}

export async function fetchAnneesArchivees(): Promise<AnneeArchivee[]> {
  const { data } = await http.get<ApiResponse<AnneeArchivee[]>>('/archives/annees')
  return data.data
}

export async function fetchClassesArchivees(anneeId: number): Promise<ClasseArchivee[]> {
  const { data } = await http.get<ApiResponse<ClasseArchivee[]>>(`/archives/annees/${anneeId}/classes`)
  return data.data
}

export async function fetchArchiveClasse(anneeId: number, classeId: number): Promise<DetailArchiveClasse> {
  const { data } = await http.get<ApiResponse<DetailArchiveClasse>>(`/archives/annees/${anneeId}/classes/${classeId}`)
  return data.data
}
