import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export type TypeTraitement = 'interne' | 'externe' | 'mixte'

export interface MalaiseReferentiel {
  id: number
  label_fr: string
  label_en: string | null
  school_id: number
  school?: { id: number; name: string } | null
}

export interface MalaiseReferentielPayload {
  label_fr: string
  label_en?: string | null
  school_id?: number
}

export interface MaterielVisite {
  id: number
  inventaire_article_id: number | null
  nom: string
  quantite: number
  cout_unitaire: number
  cout: number
  article_disponible: boolean | null
}

export interface MaterielVisitePayload {
  inventaire_article_id: number
  quantite: number
}

export interface VisiteInfirmerie {
  id: number
  eleve: { id: number; nom_complet: string }
  classe: { id: number; nom: string } | null
  school?: { id: number; name: string; code: string; type: string } | null
  date_visite: string
  raison: string
  malaises: { id: number; label_fr: string; label_en: string | null }[]
  soins_prodiges: string
  type_traitement: TypeTraitement
  structure_externe: string | null
  materiels: MaterielVisite[]
  autre_materiel: string | null
  cout_autre_materiel: number
  cout_soins: number
  cout_materiels: number
  cout_total: number
  observations: string | null
  enregistre_par: string | null
  created_at: string | null
  updated_at: string | null
}

export interface VisiteInfirmeriePayload {
  eleve_id: number
  date_visite: string
  raison: string
  malaise_ids?: number[]
  soins_prodiges: string
  type_traitement: TypeTraitement
  structure_externe?: string | null
  cout_soins?: number | null
  materiels?: MaterielVisitePayload[]
  autre_materiel?: string | null
  cout_autre_materiel?: number | null
  observations?: string | null
}

export interface VisitesInfirmerieParams {
  eleve_id?: number
  classe_id?: number
  du?: string
  au?: string
}

export async function fetchVisitesInfirmerie(params: VisitesInfirmerieParams = {}): Promise<VisiteInfirmerie[]> {
  const { data } = await http.get<ApiResponse<VisiteInfirmerie[]>>('/infirmerie/visites', { params })
  return data.data
}

export async function createVisiteInfirmerie(payload: VisiteInfirmeriePayload): Promise<VisiteInfirmerie> {
  const { data } = await http.post<ApiResponse<VisiteInfirmerie>>('/infirmerie/visites', payload)
  return data.data
}

export async function updateVisiteInfirmerie(id: number, payload: VisiteInfirmeriePayload): Promise<VisiteInfirmerie> {
  const { data } = await http.put<ApiResponse<VisiteInfirmerie>>(`/infirmerie/visites/${id}`, payload)
  return data.data
}

export async function deleteVisiteInfirmerie(id: number): Promise<void> {
  await http.delete(`/infirmerie/visites/${id}`)
}

/**
 * Scopée à l'école de l'élève sélectionné (pas au périmètre ambiant) : un
 * super admin en mode agrégé verrait sinon les référentiels des 3 écoles du
 * complexe mélangés — chacune ayant ses propres doublons de « Fièvre »,
 * « Toux »... — plutôt que la seule liste pertinente pour cette visite.
 */
export async function fetchMalaisesReferentiel(schoolId?: number): Promise<MalaiseReferentiel[]> {
  const { data } = await http.get<ApiResponse<MalaiseReferentiel[]>>('/infirmerie/malaises', {
    headers: schoolId ? { 'X-School-Id': String(schoolId) } : undefined,
  })
  return data.data
}

export async function createMalaiseReferentiel(payload: MalaiseReferentielPayload): Promise<MalaiseReferentiel> {
  const { data } = await http.post<ApiResponse<MalaiseReferentiel>>('/infirmerie/malaises', payload)
  return data.data
}

export async function deleteMalaiseReferentiel(id: number): Promise<void> {
  await http.delete(`/infirmerie/malaises/${id}`)
}
