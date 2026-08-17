import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface BusVehicule {
  id: number
  immatriculation: string
  marque: string | null
  capacite: number
  statut: 'actif' | 'hors_service'
  chauffeur: { id: number; nom_complet: string; telephone: string | null } | null
}

export interface BusVehiculePayload {
  immatriculation: string
  marque?: string | null
  capacite?: number | null
  chauffeur_id?: number | null
  statut?: BusVehicule['statut']
}

export interface BusArret {
  id: number
  nom: string
  ordre: number
  heure_passage: string | null
}

export interface BusArretPayload {
  nom: string
  ordre?: number | null
  heure_passage?: string | null
}

export interface BusTrajet {
  id: number
  nom: string
  description: string | null
  vehicule: { id: number; immatriculation: string } | null
  arrets: BusArret[]
  effectif: number | null
}

export interface BusTrajetDetail extends BusTrajet {
  affectations: {
    id: number
    statut: 'actif' | 'suspendu'
    tarif_mensuel: number | null
    eleve: { id: number; nom_complet: string; matricule: string | null }
    arret: { id: number; nom: string } | null
  }[]
}

export interface BusTrajetPayload {
  nom: string
  description?: string | null
  vehicule_id?: number | null
}

export interface BusAffectation {
  id: number
  statut: 'actif' | 'suspendu'
  tarif_mensuel: number | null
  eleve: { id: number; nom_complet: string; matricule: string | null; classe: string | null }
  trajet: { id: number; nom: string }
  arret: { id: number; nom: string } | null
}

export interface BusAffectationPayload {
  eleve_id: number
  trajet_id: number
  arret_id?: number | null
  annee_scolaire_id?: number | null
  tarif_mensuel?: number | null
}

// ---- Véhicules ---------------------------------------------------------

export async function fetchVehicules(): Promise<BusVehicule[]> {
  const { data } = await http.get<ApiResponse<BusVehicule[]>>('/bus/vehicules')
  return data.data
}

export async function creerVehicule(payload: BusVehiculePayload): Promise<BusVehicule> {
  const { data } = await http.post<ApiResponse<BusVehicule>>('/bus/vehicules', payload)
  return data.data
}

export async function modifierVehicule(id: number, payload: BusVehiculePayload): Promise<BusVehicule> {
  const { data } = await http.put<ApiResponse<BusVehicule>>(`/bus/vehicules/${id}`, payload)
  return data.data
}

export async function supprimerVehicule(id: number): Promise<void> {
  await http.delete(`/bus/vehicules/${id}`)
}

// ---- Trajets et arrêts --------------------------------------------------

export async function fetchTrajets(): Promise<BusTrajet[]> {
  const { data } = await http.get<ApiResponse<BusTrajet[]>>('/bus/trajets')
  return data.data
}

export async function fetchTrajet(id: number): Promise<BusTrajetDetail> {
  const { data } = await http.get<ApiResponse<BusTrajetDetail>>(`/bus/trajets/${id}`)
  return data.data
}

export async function creerTrajet(payload: BusTrajetPayload): Promise<BusTrajet> {
  const { data } = await http.post<ApiResponse<BusTrajet>>('/bus/trajets', payload)
  return data.data
}

export async function modifierTrajet(id: number, payload: BusTrajetPayload): Promise<BusTrajet> {
  const { data } = await http.put<ApiResponse<BusTrajet>>(`/bus/trajets/${id}`, payload)
  return data.data
}

export async function supprimerTrajet(id: number): Promise<void> {
  await http.delete(`/bus/trajets/${id}`)
}

export async function ajouterArret(trajetId: number, payload: BusArretPayload): Promise<BusArret> {
  const { data } = await http.post<ApiResponse<BusArret>>(`/bus/trajets/${trajetId}/arrets`, payload)
  return data.data
}

export async function modifierArret(trajetId: number, arretId: number, payload: BusArretPayload): Promise<BusArret> {
  const { data } = await http.put<ApiResponse<BusArret>>(`/bus/trajets/${trajetId}/arrets/${arretId}`, payload)
  return data.data
}

export async function supprimerArret(trajetId: number, arretId: number): Promise<void> {
  await http.delete(`/bus/trajets/${trajetId}/arrets/${arretId}`)
}

// ---- Affectation des élèves ----------------------------------------------

export async function fetchAffectations(trajetId?: number): Promise<BusAffectation[]> {
  const { data } = await http.get<ApiResponse<BusAffectation[]>>('/bus/affectations', {
    params: trajetId ? { trajet_id: trajetId } : undefined,
  })
  return data.data
}

export async function affecterEleve(payload: BusAffectationPayload): Promise<BusAffectation> {
  const { data } = await http.post<ApiResponse<BusAffectation>>('/bus/affectations', payload)
  return data.data
}

export async function modifierAffectation(
  id: number,
  payload: { arret_id?: number | null; tarif_mensuel?: number | null; statut?: BusAffectation['statut'] },
): Promise<BusAffectation> {
  const { data } = await http.put<ApiResponse<BusAffectation>>(`/bus/affectations/${id}`, payload)
  return data.data
}

export async function retirerAffectation(id: number): Promise<void> {
  await http.delete(`/bus/affectations/${id}`)
}
