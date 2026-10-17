import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface NoteCellule {
  eleve_id: number
  nom_complet: string
  note_id: number | null
  valeur: number | null
  observation: string | null
}

export async function fetchGrilleNotes(classeMatiereId: number, sequenceId: number): Promise<NoteCellule[]> {
  const { data } = await http.get<ApiResponse<NoteCellule[]>>(`/classe-matieres/${classeMatiereId}/notes`, {
    params: { sequence_id: sequenceId },
  })
  return data.data
}

export async function sauvegarderNotes(
  classeMatiereId: number,
  sequenceId: number,
  notes: { eleve_id: number; valeur: number | null; observation?: string | null }[],
): Promise<{ saved: number }> {
  const { data } = await http.post<ApiResponse<{ saved: number }>>(`/classe-matieres/${classeMatiereId}/notes`, {
    sequence_id: sequenceId,
    notes,
  })
  return data.data
}

/**
 * Point 16 de la punch-list : une appréciation courte (60 caractères) par
 * élève, uniquement sur la 1re ou la 2e séquence d'un trimestre (le serveur
 * rejette toute autre séquence avec un 422 — cf. AppreciationSequenceService).
 */
export interface AppreciationCellule {
  eleve_id: number
  nom_complet: string
  id: number | null
  texte: string | null
}

export async function fetchAppreciationsSequence(classeMatiereId: number, sequenceId: number): Promise<AppreciationCellule[]> {
  const { data } = await http.get<ApiResponse<AppreciationCellule[]>>(`/classe-matieres/${classeMatiereId}/appreciations-sequence`, {
    params: { sequence_id: sequenceId },
  })
  return data.data
}

export async function sauvegarderAppreciationsSequence(
  classeMatiereId: number,
  sequenceId: number,
  appreciations: { eleve_id: number; texte: string | null }[],
): Promise<{ saved: number }> {
  const { data } = await http.post<ApiResponse<{ saved: number }>>(
    `/classe-matieres/${classeMatiereId}/appreciations-sequence`,
    { sequence_id: sequenceId, appreciations },
  )
  return data.data
}
