import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export type TypeItem = 'module' | 'chapitre' | 'lecon'

/** Un élément du programme peut en contenir d'autres : modules → chapitres → leçons. */
export interface ProgressionItem {
  id?: number
  type: TypeItem
  titre: string
  description?: string | null
  sequence_id?: number | null
  duree_prevue?: number | null
  // Fiche de préparation, propre aux leçons.
  objectifs?: string | null
  materiel?: string | null
  activites?: string | null
  devoirs?: string | null
  sequence?: { id: number; libelle: string; trimestre: string | null; numero: number } | null
  traitee?: boolean | null
  seances_count?: number
  enfants: ProgressionItem[]
}

export interface Programme {
  classe: { id: number; nom: string }
  matiere: { id: number; nom: string }
  items: ProgressionItem[]
  lecons: number
  traitees: number
  taux: number
}

export async function fetchProgramme(classeMatiereId: number): Promise<Programme> {
  const { data } = await http.get<ApiResponse<Programme>>(`/classe-matieres/${classeMatiereId}/progression`)
  return data.data
}

export async function enregistrerProgramme(classeMatiereId: number, items: ProgressionItem[]): Promise<Programme> {
  const { data } = await http.put<ApiResponse<Programme>>(`/classe-matieres/${classeMatiereId}/progression`, { items })
  return data.data
}

export interface TauxMatiere {
  classe_matiere_id: number
  matiere: string
  enseignant: string | null
  lecons: number
  traitees: number
  taux: number
}

export interface TauxClasse {
  classe_id: number
  classe: string
  niveau: string | null
  lecons: number
  traitees: number
  taux: number
  matieres: TauxMatiere[]
}

export async function fetchProgressionEtablissement(): Promise<TauxClasse[]> {
  const { data } = await http.get<ApiResponse<TauxClasse[]>>('/progression')
  return data.data
}

export async function fetchProgressionClasse(classeId: number): Promise<TauxMatiere[]> {
  const { data } = await http.get<ApiResponse<TauxMatiere[]>>(`/classes/${classeId}/progression`)
  return data.data
}

/* ------------------------------------------------------------------ */
/* Ma journée                                                          */
/* ------------------------------------------------------------------ */

export type MotifAbsence = 'maladie' | 'inconnu' | 'scolarite' | 'permission'

export const MOTIFS: Record<MotifAbsence, string> = {
  maladie: 'Maladie',
  inconnu: 'Inconnu',
  scolarite: 'Scolarité',
  permission: 'Permission',
}

export interface AffectationJournee {
  classe_matiere_id: number
  classe_id: number
  classe: string
  matiere: string
}

export interface LigneAppel {
  eleve_id: number
  nom_complet: string
  matricule: string | null
  statut: 'present' | 'absent' | 'retard' | 'renvoye'
  motif: MotifAbsence | null
  pointe: boolean
}

export interface ChampPersonnaliseDef {
  id: number
  libelle: string
  type: 'texte' | 'nombre' | 'case'
}

export interface FeuilleJournee {
  seance: {
    id: number
    date: string
    heure_debut: string
    heure_fin: string
    statut: string
    observations: string | null
    donnees_personnalisees: Record<string, string | number | boolean>
  }
  lecons: {
    id: number
    titre: string
    chemin: string
    sequence: string | null
    faite_aujourdhui: boolean
    deja_traitee: boolean
  }[]
  appel: LigneAppel[]
  champs_personnalises: ChampPersonnaliseDef[]
}

export async function fetchMesAffectations(): Promise<AffectationJournee[]> {
  const { data } = await http.get<ApiResponse<AffectationJournee[]>>('/ma-journee')
  return data.data
}

export async function fetchFeuilleJournee(classeMatiereId: number, date?: string): Promise<FeuilleJournee> {
  const { data } = await http.get<ApiResponse<FeuilleJournee>>(`/ma-journee/${classeMatiereId}`, {
    params: date ? { date } : undefined,
  })
  return data.data
}

export async function enregistrerJournee(
  classeMatiereId: number,
  payload: {
    date?: string
    lecons: number[]
    appel: { eleve_id: number; statut: string; motif: MotifAbsence | null }[]
    observations?: string | null
    donnees_personnalisees?: Record<string, string | number | boolean>
  },
): Promise<FeuilleJournee> {
  const { data } = await http.post<ApiResponse<FeuilleJournee>>(`/ma-journee/${classeMatiereId}`, payload)
  return data.data
}

/** Heures de cours prévues vs réalisées de l'enseignant connecté, depuis le début de l'année. */
export interface HeuresCouverture {
  heures_prevues: number
  heures_realisees: number
  taux: number
  seances_en_retard: number
}

export async function fetchHeuresCouverture(): Promise<HeuresCouverture> {
  const { data } = await http.get<ApiResponse<HeuresCouverture>>('/ma-journee/couverture')
  return data.data
}

/** Résout le cours en cours dans une salle à partir de son QR code. */
export async function resoudreQr(token: string): Promise<{ classe_matiere_id: number; classe: string; matiere: string }> {
  const { data } = await http.get<ApiResponse<{ classe_matiere_id: number; classe: string; matiere: string }>>(
    `/ma-journee/qr/${token}`,
  )
  return data.data
}

/* ------------------------------------------------------------------ */
/* Champs personnalisés (tableaux d'informations spécifiques)          */
/* ------------------------------------------------------------------ */

export async function fetchChampsPersonnalises(classeMatiereId: number): Promise<ChampPersonnaliseDef[]> {
  const { data } = await http.get<ApiResponse<ChampPersonnaliseDef[]>>(
    `/classe-matieres/${classeMatiereId}/champs-personnalises`,
  )
  return data.data
}

export async function enregistrerChampsPersonnalises(
  classeMatiereId: number,
  champs: { id?: number; libelle: string; type: 'texte' | 'nombre' | 'case' }[],
): Promise<ChampPersonnaliseDef[]> {
  const { data } = await http.put<ApiResponse<ChampPersonnaliseDef[]>>(
    `/classe-matieres/${classeMatiereId}/champs-personnalises`,
    { champs },
  )
  return data.data
}

/* ------------------------------------------------------------------ */
/* Évaluations                                                         */
/* ------------------------------------------------------------------ */

export type TypeEvaluation = 'interrogation' | 'devoir' | 'examen'

export interface EvaluationQuestion {
  id?: number
  enonce: string
  bareme_question: number
}

export interface Evaluation {
  id: number
  titre: string
  type: TypeEvaluation
  date_prevue: string | null
  bareme: number
  competences: string | null
  progression_item_id: number | null
  lecon: string | null
  cree_par: string | null
  bareme_questions: number
  questions: EvaluationQuestion[]
}

export interface EvaluationPayload {
  titre: string
  type: TypeEvaluation
  date_prevue: string | null
  bareme: number
  competences: string | null
  progression_item_id: number | null
  questions: EvaluationQuestion[]
}

export async function fetchEvaluations(classeMatiereId: number): Promise<Evaluation[]> {
  const { data } = await http.get<ApiResponse<Evaluation[]>>(`/classe-matieres/${classeMatiereId}/evaluations`)
  return data.data
}

export async function creerEvaluation(classeMatiereId: number, payload: EvaluationPayload): Promise<Evaluation> {
  const { data } = await http.post<ApiResponse<Evaluation>>(`/classe-matieres/${classeMatiereId}/evaluations`, payload)
  return data.data
}

export async function modifierEvaluation(id: number, payload: EvaluationPayload): Promise<Evaluation> {
  const { data } = await http.put<ApiResponse<Evaluation>>(`/evaluations/${id}`, payload)
  return data.data
}

export async function supprimerEvaluation(id: number): Promise<void> {
  await http.delete(`/evaluations/${id}`)
}
