import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

/** Volets d'évaluation du primaire (le secondaire n'a qu'une note par séquence). */
export type Composante = 'oral' | 'ecrit' | 'savoir_etre' | 'pratique'

export const LIBELLES_COMPOSANTES: Record<Composante, string> = {
  oral: 'Oral',
  ecrit: 'Écrit',
  savoir_etre: 'Savoir-être',
  pratique: 'Pratique',
}

export interface NiveauScolaire {
  id: number
  code: string
  libelle: string
  ordre: number
  animateur_personnel_id: number | null
  animateur: { id: number; nom_complet: string } | null
  nb_classes?: number
  school_id?: number
  school?: { id: number; name: string; code: string; type: 'maternelle' | 'primaire' | 'secondaire' } | null
}

export interface NiveauScolairePayload {
  code: string
  libelle: string
  ordre?: number | null
  animateur_personnel_id?: number | null
  school_id?: number | null
}

export async function fetchNiveauxScolaires(): Promise<NiveauScolaire[]> {
  const { data } = await http.get<ApiResponse<NiveauScolaire[]>>('/niveaux-scolaires')
  return data.data
}

export async function createNiveauScolaire(payload: NiveauScolairePayload): Promise<NiveauScolaire> {
  const { data } = await http.post<ApiResponse<NiveauScolaire>>('/niveaux-scolaires', payload)
  return data.data
}

export async function updateNiveauScolaire(id: number, payload: NiveauScolairePayload): Promise<NiveauScolaire> {
  const { data } = await http.put<ApiResponse<NiveauScolaire>>(`/niveaux-scolaires/${id}`, payload)
  return data.data
}

export async function deleteNiveauScolaire(id: number): Promise<void> {
  await http.delete(`/niveaux-scolaires/${id}`)
}

/* ------------------------------------------------------------------ */
/* Saisie des notes par volets                                         */
/* ------------------------------------------------------------------ */

/**
 * Niveau d'appréciation de la maternelle : le visage coché à la saisie, la
 * couleur dont le bulletin remplit sa case.
 */
export interface Appreciation {
  id: number
  label_fr: string
  label_en: string | null
  emoji: string | null
  couleur: string
  ordre: number
  statut?: string
  school_id?: number
  school?: { id: number; name: string; type: string } | null
}

export interface AppreciationPayload {
  label_fr: string
  label_en?: string | null
  emoji?: string | null
  couleur: string
  ordre: number
  statut?: string
  school_id?: number | null
}

export interface GrillePrimaire {
  /**
   * « note » au primaire (saisie chiffrée), « appreciation » en maternelle
   * (on coche un visage). C'est l'API qui tranche, d'après l'école de la classe.
   */
  mode: 'note' | 'appreciation'
  composantes: Composante[]
  sequences: { id: number; libelle: string }[]
  bareme: number
  /** Points maximum par volet, tels que définis sur la compétence. */
  repartition: Record<Composante, number>
  /** Colonnes de la maternelle, dans l'ordre ; vide au primaire. */
  appreciations: Appreciation[]
  lignes: {
    eleve_id: number
    nom_complet: string
    /** notes[composante][sequence_id] — primaire. */
    notes: Record<Composante, Record<number, number | null>>
    /** appreciations[composante][sequence_id] — maternelle. */
    appreciations: Record<Composante, Record<number, number | null>>
  }[]
}

export interface NotePrimaireInput {
  eleve_id: number
  sequence_id: number
  composante: Composante
  valeur?: number | null
  /** Renseigné en maternelle, à la place de `valeur`. */
  appreciation_id?: number | null
}

/** La grille porte sur une compétence : c'est elle que le bulletin note. */
export async function fetchGrillePrimaire(classeCompetenceId: number, trimestreId?: number): Promise<GrillePrimaire> {
  const { data } = await http.get<ApiResponse<GrillePrimaire>>(
    `/classe-competences/${classeCompetenceId}/notes-primaire`,
    { params: trimestreId ? { trimestre_id: trimestreId } : undefined },
  )
  return data.data
}

export async function sauvegarderNotesPrimaire(
  classeCompetenceId: number,
  notes: NotePrimaireInput[],
): Promise<{ saved: number }> {
  const { data } = await http.post<ApiResponse<{ saved: number }>>(
    `/classe-competences/${classeCompetenceId}/notes-primaire`,
    { notes },
  )
  return data.data
}

/* ------------------------------------------------------------------ */
/* Résultats                                                           */
/* ------------------------------------------------------------------ */

export interface LigneClassementPrimaire {
  eleve_id: number
  nom_complet: string
  matricule: string | null
  sexe: 'M' | 'F'
  moyenne: number | null
  rang: number | null
}

export async function fetchClassementPrimaire(classeId: number, trimestreId?: number): Promise<LigneClassementPrimaire[]> {
  const { data } = await http.get<ApiResponse<LigneClassementPrimaire[]>>(`/classes/${classeId}/classement-primaire`, {
    params: trimestreId ? { trimestre_id: trimestreId } : undefined,
  })
  return data.data
}

export interface LigneRemplissagePrimaire {
  classe_competence_id: number
  competence: string
  competence_en: string | null
  bareme: number
  volets: Composante[]
  enseignant: string | null
  taux: number
}

export async function fetchRemplissagePrimaire(
  classeId: number,
  trimestreId?: number,
): Promise<LigneRemplissagePrimaire[]> {
  const { data } = await http.get<ApiResponse<{ competences: LigneRemplissagePrimaire[] }>>(
    `/classes/${classeId}/remplissage-primaire`,
    { params: trimestreId ? { trimestre_id: trimestreId } : undefined },
  )
  return data.data.competences
}

export interface LigneDecision {
  eleve_id: number
  nom_complet: string
  matricule: string | null
  moyenne_annuelle: number | null
  decision: 'admis' | 'redouble' | 'en_attente'
}

export async function fetchDecisions(classeId: number): Promise<LigneDecision[]> {
  const { data } = await http.get<ApiResponse<LigneDecision[]>>(`/classes/${classeId}/decisions`)
  return data.data
}

/* ------------------------------------------------------------------ */
/* Référentiel d'appréciations — maternelle                            */
/* ------------------------------------------------------------------ */

/**
 * Le référentiel se dote de ses niveaux d'usage à la première lecture : une
 * école qui ouvre l'écran de saisie peut cocher un visage sans réglage.
 */
export async function fetchAppreciations(): Promise<Appreciation[]> {
  const { data } = await http.get<ApiResponse<Appreciation[]>>('/appreciations')
  return data.data
}

export async function creerAppreciation(payload: AppreciationPayload): Promise<Appreciation> {
  const { data } = await http.post<ApiResponse<Appreciation>>('/appreciations', payload)
  return data.data
}

export async function modifierAppreciation(id: number, payload: AppreciationPayload): Promise<Appreciation> {
  const { data } = await http.put<ApiResponse<Appreciation>>(`/appreciations/${id}`, payload)
  return data.data
}

export async function supprimerAppreciation(id: number): Promise<void> {
  await http.delete(`/appreciations/${id}`)
}
