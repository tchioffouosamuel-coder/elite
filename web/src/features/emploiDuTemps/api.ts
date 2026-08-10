import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export const JOURS = [
  { valeur: 1, libelle: 'Lundi' },
  { valeur: 2, libelle: 'Mardi' },
  { valeur: 3, libelle: 'Mercredi' },
  { valeur: 4, libelle: 'Jeudi' },
  { valeur: 5, libelle: 'Vendredi' },
  { valeur: 6, libelle: 'Samedi' },
] as const

export interface Creneau {
  id: number
  jour: number
  heure_debut: string
  heure_fin: string
  salle: string | null
  classe_matiere_id: number
  matiere: string | null
  enseignant: string | null
}

export interface CreneauPayload {
  classe_matiere_id: number
  jour: number
  heure_debut: string
  heure_fin: string
  salle?: string | null
}

export interface Seance {
  id: number
  classe_id: number
  classe_matiere_id: number
  matiere: string | null
  enseignant: string | null
  date_seance: string
  heure_debut: string
  heure_fin: string
  salle: string | null
  contenu: string | null
  statut: 'prevue' | 'effectuee' | 'annulee'
  absents: number
}

export interface LigneAppel {
  eleve_id: number
  nom_complet: string
  matricule: string | null
  statut: 'present' | 'absent' | 'retard' | 'renvoye'
  justifie: boolean
  remarque: string | null
  pointe: boolean
}

export async function fetchEmploiDuTemps(classeId: number): Promise<Creneau[]> {
  const { data } = await http.get<ApiResponse<Creneau[]>>(`/classes/${classeId}/emploi-du-temps`)
  return data.data
}

export async function createCreneau(classeId: number, payload: CreneauPayload): Promise<Creneau> {
  const { data } = await http.post<ApiResponse<Creneau>>(`/classes/${classeId}/emploi-du-temps`, payload)
  return data.data
}

export async function updateCreneau(classeId: number, id: number, payload: CreneauPayload): Promise<Creneau> {
  const { data } = await http.put<ApiResponse<Creneau>>(`/classes/${classeId}/emploi-du-temps/${id}`, payload)
  return data.data
}

export async function deleteCreneau(classeId: number, id: number): Promise<void> {
  await http.delete(`/classes/${classeId}/emploi-du-temps/${id}`)
}

export async function genererSeances(
  classeId: number,
  payload: { date_debut: string; date_fin: string; trimestre_id?: number },
): Promise<{ creees: number }> {
  const { data } = await http.post<ApiResponse<{ creees: number }>>(
    `/classes/${classeId}/emploi-du-temps/generer-seances`,
    payload,
  )
  return data.data
}

export async function fetchSeances(
  classeId: number,
  params?: { date_debut?: string; date_fin?: string; trimestre_id?: number },
): Promise<Seance[]> {
  const { data } = await http.get<ApiResponse<Seance[]>>(`/classes/${classeId}/seances`, { params })
  return data.data
}

export async function fetchAppel(seanceId: number): Promise<{ seance: Seance; lignes: LigneAppel[] }> {
  const { data } = await http.get<ApiResponse<{ seance: Seance; lignes: LigneAppel[] }>>(`/seances/${seanceId}/appel`)
  return data.data
}

export async function enregistrerAppel(
  seanceId: number,
  lignes: Pick<LigneAppel, 'eleve_id' | 'statut' | 'justifie' | 'remarque'>[],
): Promise<{ enregistres: number }> {
  const { data } = await http.post<ApiResponse<{ enregistres: number }>>(`/seances/${seanceId}/appel`, { lignes })
  return data.data
}

export async function updateSeance(
  seanceId: number,
  payload: { contenu?: string | null; salle?: string | null; statut?: Seance['statut'] },
): Promise<Seance> {
  const { data } = await http.put<ApiResponse<Seance>>(`/seances/${seanceId}`, payload)
  return data.data
}
