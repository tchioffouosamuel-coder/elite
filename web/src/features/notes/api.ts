import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface NoteCellule {
  eleve_id: number
  nom_complet: string
  note_id: number | null
  valeur: number | null
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
  notes: { eleve_id: number; valeur: number | null }[],
): Promise<{ saved: number }> {
  const { data } = await http.post<ApiResponse<{ saved: number }>>(`/classe-matieres/${classeMatiereId}/notes`, {
    sequence_id: sequenceId,
    notes,
  })
  return data.data
}
