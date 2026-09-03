import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface DecisionConseil {
  id: number
  eleve: { id: number; matricule: string | null; nom_complet: string }
  moyenne_annuelle: number | null
  decision_defaut: 'admis' | 'redouble'
  decision_finale: 'admis' | 'redouble' | 'exclu'
  gracie: boolean
  motif: string | null
}

export interface ConseilClasse {
  id: number
  classe: { id: number; nom: string }
  annee_scolaire: { id: number; libelle: string }
  seuil_moyenne: number
  motif_seuil: string | null
  classe_destination: { id: number; nom: string } | null
  statut: 'brouillon' | 'valide'
  valide_le: string | null
  decisions: DecisionConseil[]
}

export async function fetchConseilClasse(classeId: number, anneeScolaireId?: number): Promise<ConseilClasse> {
  const { data } = await http.get<ApiResponse<ConseilClasse>>(`/classes/${classeId}/conseil`, {
    params: anneeScolaireId ? { annee_scolaire_id: anneeScolaireId } : undefined,
  })
  return data.data
}

export async function definirSeuilConseil(id: number, seuilMoyenne: number, motif?: string): Promise<ConseilClasse> {
  const { data } = await http.put<ApiResponse<ConseilClasse>>(`/conseils-classe/${id}/seuil`, {
    seuil_moyenne: seuilMoyenne,
    motif,
  })
  return data.data
}

export async function definirDestinationConseil(id: number, classeDestinationId: number | null): Promise<ConseilClasse> {
  const { data } = await http.post<ApiResponse<ConseilClasse>>(`/conseils-classe/${id}/destination`, {
    classe_destination_id: classeDestinationId,
  })
  return data.data
}

export async function exclureDecision(decisionId: number, motif: string): Promise<ConseilClasse> {
  const { data } = await http.post<ApiResponse<ConseilClasse>>(`/conseil-classe-decisions/${decisionId}/exclure`, { motif })
  return data.data
}

export async function gracierDecision(decisionId: number, motif: string): Promise<ConseilClasse> {
  const { data } = await http.post<ApiResponse<ConseilClasse>>(`/conseil-classe-decisions/${decisionId}/gracier`, { motif })
  return data.data
}

export async function annulerAjustementDecision(decisionId: number): Promise<ConseilClasse> {
  const { data } = await http.post<ApiResponse<ConseilClasse>>(`/conseil-classe-decisions/${decisionId}/annuler-ajustement`)
  return data.data
}

export async function validerConseil(id: number): Promise<ConseilClasse> {
  const { data } = await http.post<ApiResponse<ConseilClasse>>(`/conseils-classe/${id}/valider`)
  return data.data
}
