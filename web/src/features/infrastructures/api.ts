import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export type TypeInfrastructure =
  | 'salle_classe'
  | 'bloc_administratif'
  | 'wc'
  | 'cloture'
  | 'point_eau'
  | 'electricite'
  | 'aire_jeu'
  | 'logement_maitre'
  | 'autre'
export type MateriauInfrastructure = 'dur' | 'semi_dur' | 'provisoire'
export type EtatInfrastructure = 'bon' | 'assez_bon' | 'mauvais'

export interface Infrastructure {
  id: number
  type: TypeInfrastructure
  libelle: string | null
  materiau: MateriauInfrastructure | null
  etat: EtatInfrastructure | null
  quantite: number
  besoin_quantite: number | null
  observations: string | null
  school_id: number
}

export interface InfrastructurePayload {
  type: TypeInfrastructure
  libelle?: string | null
  materiau?: MateriauInfrastructure | null
  etat?: EtatInfrastructure | null
  quantite: number
  besoin_quantite?: number | null
  observations?: string | null
  school_id?: number | null
}

export interface EquipementMobilier {
  id: number
  nature: string
  quantite: number
  besoin_quantite: number | null
  school_id: number
}

export interface EquipementMobilierPayload {
  nature: string
  quantite: number
  besoin_quantite?: number | null
  school_id?: number | null
}

/** Grille matériau × état, une clé par matériau puis par état, valeur = quantité cumulée. */
export type GrilleMateriauEtat = Record<MateriauInfrastructure, Record<EtatInfrastructure, number>>

export interface RapportInfrastructures {
  salles_classe: GrilleMateriauEtat
  bloc_administratif: GrilleMateriauEtat
  autres: Record<string, number>
  equipements: EquipementMobilier[]
}

export async function fetchInfrastructures(): Promise<Infrastructure[]> {
  const { data } = await http.get<ApiResponse<Infrastructure[]>>('/infrastructures')
  return data.data
}

export async function creerInfrastructure(payload: InfrastructurePayload): Promise<Infrastructure> {
  const { data } = await http.post<ApiResponse<Infrastructure>>('/infrastructures', payload)
  return data.data
}

export async function modifierInfrastructure(id: number, payload: Partial<InfrastructurePayload>): Promise<Infrastructure> {
  const { data } = await http.put<ApiResponse<Infrastructure>>(`/infrastructures/${id}`, payload)
  return data.data
}

export async function supprimerInfrastructure(id: number): Promise<void> {
  await http.delete(`/infrastructures/${id}`)
}

export async function fetchEquipements(): Promise<EquipementMobilier[]> {
  const { data } = await http.get<ApiResponse<EquipementMobilier[]>>('/infrastructures/equipements')
  return data.data
}

export async function creerEquipement(payload: EquipementMobilierPayload): Promise<EquipementMobilier> {
  const { data } = await http.post<ApiResponse<EquipementMobilier>>('/infrastructures/equipements', payload)
  return data.data
}

export async function modifierEquipement(id: number, payload: Partial<EquipementMobilierPayload>): Promise<EquipementMobilier> {
  const { data } = await http.put<ApiResponse<EquipementMobilier>>(`/infrastructures/equipements/${id}`, payload)
  return data.data
}

export async function supprimerEquipement(id: number): Promise<void> {
  await http.delete(`/infrastructures/equipements/${id}`)
}

export async function fetchRapportInfrastructures(): Promise<RapportInfrastructures> {
  const { data } = await http.get<ApiResponse<RapportInfrastructures>>('/infrastructures/rapport')
  return data.data
}
