import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export type RubriqueTexteTrimestre =
  | 'introduction'
  | 'observations_structure'
  | 'observations_eleves'
  | 'observations_personnel'
  | 'difficultes_rencontrees'
  | 'conclusion_generale'

export type TextesTrimestre = Record<RubriqueTexteTrimestre, string | null>

export async function fetchTextesTrimestre(trimestreId?: number): Promise<TextesTrimestre> {
  const { data } = await http.get<ApiResponse<TextesTrimestre>>('/rapport-trimestre-textes', {
    params: trimestreId ? { trimestre_id: trimestreId } : undefined,
  })
  return data.data
}

export async function definirTexteTrimestre(
  rubrique: RubriqueTexteTrimestre,
  trimestreId: number,
  contenu: string,
): Promise<void> {
  await http.put(`/rapport-trimestre-textes/${rubrique}`, { trimestre_id: trimestreId, contenu })
}
