import { http } from '@/shared/lib/http'
import { telechargerFichier } from '@/shared/lib/download'
import type { ApiResponse } from '@/shared/types/api'

export type TypeItem = 'module' | 'chapitre' | 'lecon'

export type Cycle = 'primaire' | 'secondaire'

/**
 * Champs de la fiche, communs aux deux gabarits de l'établissement — un pour
 * maternelle/primaire, un pour le secondaire. `competence` (Competency)
 * n'existe que sur le premier, `teaching_learning_strategies`
 * (Teaching / Strategy) que sur le second : l'écran n'affiche que la colonne
 * pertinente pour le cycle de l'affectation, mais les deux voyagent sur le
 * même objet côté API.
 */
export interface FicheLecon {
  topic?: string | null
  sous_topic?: string | null
  competence?: string | null
  expected_learning_outcomes?: string | null
  entry_behaviour?: string | null
  teaching_aids?: string | null
  teaching_learning_strategies?: string | null
  facilitators_activities?: string | null
  learners_activities?: string | null
  assessment?: string | null
  assignment?: string | null
  remarks?: string | null
}

/** Repères de la ligne : Week, Date Planned, Date Taught, Duration/Periods. */
export interface CalendrierLecon {
  semaine?: string | null
  date_prevue?: string | null
  date_realisee?: string | null
  duree?: string | null
}

/** Un élément du programme peut en contenir d'autres : modules → chapitres → leçons. */
export interface ProgressionItem extends FicheLecon, CalendrierLecon {
  id?: number
  type: TypeItem
  titre: string
  description?: string | null
  sequence_id?: number | null
  duree_prevue?: number | null
  // Valeurs des colonnes libres de la matière, indexées par l'id de la colonne.
  colonnes_libres?: Record<string, string | null>
  a_preparation?: boolean | null
  sequence?: { id: number; libelle: string; trimestre: string | null; numero: number } | null
  traitee?: boolean | null
  seances_count?: number
  enfants: ProgressionItem[]
}

/** Colonne libre de la fiche — jusqu'à dix par matière/classe. */
export interface ProgressionColonneDef {
  id: number
  libelle: string
  ordre: number
}

export interface Programme {
  classe: { id: number; nom: string }
  matiere: { id: number; nom: string }
  items: ProgressionItem[]
  colonnes: ProgressionColonneDef[]
  cycle: Cycle
  // Cartouche du gabarit secondaire ; toujours présents mais sans objet côté primaire.
  departement: string | null
  specialite: string | null
  module_competence: string | null
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

export interface CartouchePayload {
  specialite?: string | null
  module_competence?: string | null
}

export async function enregistrerCartouche(
  classeMatiereId: number,
  payload: CartouchePayload,
): Promise<Pick<Programme, 'departement' | 'specialite' | 'module_competence'>> {
  const { data } = await http.put<ApiResponse<Pick<Programme, 'departement' | 'specialite' | 'module_competence'>>>(
    `/classe-matieres/${classeMatiereId}/progression/cartouche`,
    payload,
  )
  return data.data
}

/* ------------------------------------------------------------------ */
/* Colonnes libres                                                     */
/* ------------------------------------------------------------------ */

export async function fetchProgressionColonnes(classeMatiereId: number): Promise<ProgressionColonneDef[]> {
  const { data } = await http.get<ApiResponse<ProgressionColonneDef[]>>(
    `/classe-matieres/${classeMatiereId}/progression-colonnes`,
  )
  return data.data
}

export async function enregistrerProgressionColonnes(
  classeMatiereId: number,
  colonnes: { id?: number; libelle: string }[],
): Promise<ProgressionColonneDef[]> {
  const { data } = await http.put<ApiResponse<ProgressionColonneDef[]>>(
    `/classe-matieres/${classeMatiereId}/progression-colonnes`,
    { colonnes },
  )
  return data.data
}

/* ------------------------------------------------------------------ */
/* Import de la feuille de progression de l'établissement              */
/* ------------------------------------------------------------------ */

/** Ce que l'import a fait du fichier, ligne par ligne. */
export interface ResultatImport extends Programme {
  creees: number
  completees: number
  ignorees: number
}

export async function importerProgression(classeMatiereId: number, fichier: File): Promise<ResultatImport> {
  const corps = new FormData()
  corps.append('fichier', fichier)

  const { data } = await http.post<ApiResponse<ResultatImport>>(
    `/classe-matieres/${classeMatiereId}/progression/import`,
    corps,
  )
  return data.data
}

/**
 * Fiche de progression en PDF, A4 paysage.
 *
 * L'API exige un Bearer token, impossible via un simple lien <a href> : on
 * ouvre un onglet vide tout de suite (dans le geste utilisateur, pour éviter
 * le blocage de pop-up), puis on y charge le PDF récupéré en blob.
 */
export async function ouvrirFicheProgressionPdf(classeMatiereId: number): Promise<void> {
  const fenetre = window.open('', '_blank')

  const response = await http.get(`/classe-matieres/${classeMatiereId}/progression/pdf`, {
    responseType: 'blob',
  })

  const blobUrl = URL.createObjectURL(response.data as Blob)

  if (fenetre) {
    fenetre.location.href = blobUrl
  } else {
    window.open(blobUrl, '_blank')
  }
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
/* Import groupé de la classe (une feuille par matière)                */
/* ------------------------------------------------------------------ */

/** Ce que l'import groupé a fait du classeur, feuille par feuille. */
export interface ResultatImportClasse {
  creees: number
  completees: number
  matieres_importees: number
  feuilles_ignorees: string[]
}

export async function telechargerModeleProgressionClasse(classeId: number, nomClasse: string): Promise<void> {
  await telechargerFichier(`/classes/${classeId}/progression/modele`, undefined, `modele-progression-${nomClasse}.xlsx`)
}

export async function importerProgressionClasse(classeId: number, fichier: File): Promise<ResultatImportClasse> {
  const corps = new FormData()
  corps.append('fichier', fichier)

  const { data } = await http.post<ApiResponse<ResultatImportClasse>>(`/classes/${classeId}/progression/import`, corps)
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
  heure_debut: string
  heure_fin: string
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

export async function fetchMesAffectations(date?: string): Promise<AffectationJournee[]> {
  const { data } = await http.get<ApiResponse<AffectationJournee[]>>('/ma-journee', {
    params: date ? { date } : undefined,
  })
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
export interface ResolutionQr {
  classe_matiere_id: number
  classe_id: number
  classe: string
  matiere: string
}

export async function resoudreQr(token: string): Promise<ResolutionQr> {
  const { data } = await http.get<ApiResponse<ResolutionQr>>(`/ma-journee/qr/${token}`)
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
