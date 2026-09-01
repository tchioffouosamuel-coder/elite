import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import type { RubriqueScolarite, StatutPaiement, ModePaiement, Echeancier } from '@/features/finance/api'

// -------------------------------------------------------------- Mes enfants

export interface EnfantResume {
  id: number
  matricule: string | null
  nom_complet: string
  sexe: 'M' | 'F'
  photo_url: string | null
  classe: { id: number; nom: string } | null
  school: { id: number; name: string } | null
}

export async function fetchMesEnfants(): Promise<EnfantResume[]> {
  const { data } = await http.get<ApiResponse<EnfantResume[]>>('/parent/enfants')
  return data.data
}

export interface TuteurTelephonePayload {
  numero: string
  is_principal?: boolean
}

export interface TuteurEnfant {
  id: number
  nom_complet: string
  telephone: string | null
  telephones: TuteurTelephonePayload[]
  email: string | null
  profession: string | null
  lieu_service: string | null
  adresse: string | null
  lien_parente: string | null
  is_principal: boolean
}

export interface EnfantDossier {
  id: number
  matricule: string | null
  nom_complet: string
  sexe: 'M' | 'F'
  date_naissance: string | null
  lieu_naissance: string | null
  nationalite: string | null
  adresse: string | null
  photo_url: string | null
  photo_tenue_url: string | null
  redoublant: boolean
  statut: string
  classe: { id: number; nom: string; sous_systeme: string | null } | null
  school: { id: number; name: string; type: string } | null
  acte_naissance: { numero: string | null; lieu_delivrance: string | null; officier_etat_civil: string | null }
  sante: { groupe_sanguin: string | null; situation_sanitaire: string | null; aptitude: 'apte' | 'inapte'; allergies: string | null }
  tuteurs: TuteurEnfant[]
}

export async function fetchEnfant(eleveId: number): Promise<EnfantDossier> {
  const { data } = await http.get<ApiResponse<EnfantDossier>>(`/parent/enfants/${eleveId}`)
  return data.data
}

// ------------------------------------------------------------------ Finance

export interface FinanceEnfant {
  montant_scolarite: number
  remise: number
  report_dette: number
  total_du: number
  total_paye: number
  reste_a_payer: number
  statut_paiement: StatutPaiement
  rubriques: RubriqueScolarite[]
  versements: { numero_recu: string; date_versement: string; montant: number; mode: ModePaiement }[]
  /** Échéancier de la scolarité ; `actif` à faux si l'école n'a pas découpé son année. */
  echeancier: Echeancier
  date_limite_paiement: string | null
  date_exclusion_insolvables: string | null
  moratoire: { date_expiration: string; motif: string | null } | null
}

export async function fetchFinanceEnfant(eleveId: number): Promise<FinanceEnfant | null> {
  const { data } = await http.get<ApiResponse<FinanceEnfant | null>>(`/parent/enfants/${eleveId}/finance`)
  return data.data
}

// --------------------------------------------------------------- Pédagogie

export interface MatiereProgression {
  classe_matiere_id: number
  matiere: string
  enseignant: string | null
  lecons: number
  traitees: number
  taux: number
}

export async function fetchProgressionEnfant(eleveId: number): Promise<MatiereProgression[]> {
  const { data } = await http.get<ApiResponse<MatiereProgression[]>>(`/parent/enfants/${eleveId}/progression`)
  return data.data
}

export interface LeconProgramme {
  id: number
  titre: string
  traitee: boolean
}

export interface ProgrammeMatiere {
  matiere: string
  lecons: LeconProgramme[]
  /** Dernière leçon traitée — là où l'enseignant s'est arrêté en classe. `null` si rien n'est encore traité. */
  derniere_lecon_id: number | null
}

export async function fetchProgrammeMatiereEnfant(eleveId: number, classeMatiereId: number): Promise<ProgrammeMatiere> {
  const { data } = await http.get<ApiResponse<ProgrammeMatiere>>(`/parent/enfants/${eleveId}/progression/${classeMatiereId}`)
  return data.data
}

export interface CreneauEmploiDuTemps {
  id: number
  jour: number
  heure_debut: string
  heure_fin: string
  salle: string | null
  matiere: string | null
  enseignant: string | null
  classe: string | null
  tronc_commun: boolean
}

export async function fetchEmploiDuTempsEnfant(eleveId: number): Promise<CreneauEmploiDuTemps[]> {
  const { data } = await http.get<ApiResponse<CreneauEmploiDuTemps[]>>(`/parent/enfants/${eleveId}/emploi-du-temps`)
  return data.data
}

// ---------------------------------------------------------------- Assiduité

export interface AbsenceEnfant {
  date: string
  statut: 'absent' | 'retard'
  motif: string | null
  justifie: boolean
  remarque: string | null
}

export async function fetchAbsencesEnfant(eleveId: number): Promise<AbsenceEnfant[]> {
  const { data } = await http.get<ApiResponse<AbsenceEnfant[]>>(`/parent/enfants/${eleveId}/absences`)
  return data.data
}

export type MotifJustification = 'maladie' | 'scolarite' | 'permission'

export interface JustificationEnfant {
  id: number
  date_debut: string
  date_fin: string
  motif: MotifJustification
  description: string | null
  statut: 'en_attente' | 'appliquee'
}

export async function fetchJustificationsEnfant(eleveId: number): Promise<JustificationEnfant[]> {
  const { data } = await http.get<ApiResponse<JustificationEnfant[]>>(`/parent/enfants/${eleveId}/justifications`)
  return data.data
}

export async function soumettreJustification(
  eleveId: number,
  payload: { date_debut: string; date_fin?: string; motif: MotifJustification; description?: string },
): Promise<JustificationEnfant> {
  const { data } = await http.post<ApiResponse<JustificationEnfant>>(`/parent/enfants/${eleveId}/justifications`, payload)
  return data.data
}

// ------------------------------------------------------------- Observations

export interface ObservationEnfant {
  id: number
  contenu: string
  auteur: string | null
  origine: 'parent' | 'ecole'
  date: string
}

export async function fetchObservationsEnfant(eleveId: number): Promise<ObservationEnfant[]> {
  const { data } = await http.get<ApiResponse<ObservationEnfant[]>>(`/parent/enfants/${eleveId}/observations`)
  return data.data
}

export async function soumettreObservation(eleveId: number, contenu: string): Promise<ObservationEnfant> {
  const { data } = await http.post<ApiResponse<ObservationEnfant>>(`/parent/enfants/${eleveId}/observations`, { contenu })
  return data.data
}

// ----------------------------------------------------------------- Infirmerie

export interface VisiteInfirmerieEnfant {
  id: number
  date_visite: string
  raison: string | null
  malaises: { id: number; label_fr: string; label_en: string }[]
  soins_prodiges: string | null
  observations: string | null
  enregistre_par: string | null
}

export async function fetchVisitesInfirmerieEnfant(eleveId: number): Promise<VisiteInfirmerieEnfant[]> {
  const { data } = await http.get<ApiResponse<VisiteInfirmerieEnfant[]>>(`/parent/enfants/${eleveId}/visites-infirmerie`)
  return data.data
}

// ----------------------------------------------------------------- Discipline

export interface SanctionEnfant {
  id: number
  type: string
  duree_jours: number | null
  date_debut: string | null
  date_fin: string | null
  motif: string
  commentaire: string | null
  date_sanction: string
  statut: string
  impacte_bulletin: boolean
  enregistre_par: string | null
}

export interface DossierDisciplinaireEnfant {
  total_sanctions: number
  sanctions_en_cours: number
  est_exclu: boolean
  motif_exclusion: string | null
  date_exclusion: string | null
  sanctions: SanctionEnfant[]
}

export async function fetchSanctionsEnfant(eleveId: number): Promise<DossierDisciplinaireEnfant> {
  const { data } = await http.get<ApiResponse<DossierDisciplinaireEnfant>>(`/parent/enfants/${eleveId}/sanctions`)
  return data.data
}

// --------------------------------------------------- Révision d'identité/santé

export interface ModificationEnfantPayload {
  nom_complet?: string
  sexe?: 'M' | 'F'
  date_naissance?: string
  lieu_naissance?: string
  adresse?: string
  numero_acte_naissance?: string
  lieu_delivrance_acte?: string
  officier_etat_civil?: string
  groupe_sanguin?: string
  situation_sanitaire?: string
  aptitude?: 'apte' | 'inapte'
  allergies?: string
}

export interface ModificationEnfantResume {
  id: number
  donnees: ModificationEnfantPayload
  statut: 'en_attente' | 'validee' | 'rejetee'
  motif_rejet: string | null
  created_at: string
  traite_le: string | null
}

export async function fetchModificationEnAttente(eleveId: number): Promise<ModificationEnfantResume | null> {
  const { data } = await http.get<ApiResponse<ModificationEnfantResume | null>>(`/parent/enfants/${eleveId}/modification`)
  return data.data
}

/** Historique complet — y compris les demandes déjà traitées, pour que le parent voie le retour donné à chacune. */
export async function fetchHistoriqueModifications(eleveId: number): Promise<ModificationEnfantResume[]> {
  const { data } = await http.get<ApiResponse<ModificationEnfantResume[]>>(`/parent/enfants/${eleveId}/modifications`)
  return data.data
}

export async function soumettreModification(eleveId: number, donnees: ModificationEnfantPayload): Promise<ModificationEnfantResume> {
  const { data } = await http.post<ApiResponse<ModificationEnfantResume>>(`/parent/enfants/${eleveId}/modification`, donnees)
  return data.data
}

// ------------------------------------------------------------ Préinscription

export type TypePreinscription = 'existant' | 'nouveau'
export type StatutPreinscription = 'en_attente' | 'validee' | 'rejetee'

export interface TuteurPayload {
  nom_complet: string
  /** @deprecated legacy shape — kept for reading old requêtes déjà soumises ; utiliser `telephones`. */
  telephone?: string
  telephones?: TuteurTelephonePayload[]
  email?: string
  profession?: string
  lieu_service?: string
  adresse?: string
  lien_parente?: string
  is_principal?: boolean
}

export interface EleveProposePayload {
  nom_complet: string
  sexe: 'M' | 'F'
  date_naissance: string
  lieu_naissance?: string
  adresse?: string
  classe_id?: number | null
  numero_acte_naissance?: string
  lieu_delivrance_acte?: string
  officier_etat_civil?: string
  groupe_sanguin?: string
  situation_sanitaire?: string
  aptitude?: 'apte' | 'inapte'
  allergies?: string
}

export interface PreinscriptionPayload {
  type: TypePreinscription
  eleve_id?: number
  school_id?: number
  donnees_eleve: EleveProposePayload
  donnees_tuteurs: TuteurPayload[]
  note_admin?: string
  montant_verser?: number
  mode_versement?: ModePaiement
  reference_externe?: string
  rubriques_versement?: { affectation: RubriqueScolarite['cle']; dossier_frais_annexe_id?: number | null; libelle?: string; montant: number }[]
}

export interface PreinscriptionResume {
  id: number
  type: TypePreinscription
  statut: StatutPreinscription
  eleve: { id?: number; nom_complet: string } | null
  nom_propose: string | null
  /** École visée — fixée au dépôt, non modifiable ensuite. */
  school: { id: number; name: string } | null
  montant_verser: number | null
  motif_rejet: string | null
  created_at: string
  traite_le: string | null
}

export async function fetchMesPreinscriptions(): Promise<PreinscriptionResume[]> {
  const { data } = await http.get<ApiResponse<PreinscriptionResume[]>>('/parent/preinscriptions')
  return data.data
}

export async function soumettrePreinscription(payload: PreinscriptionPayload): Promise<PreinscriptionResume> {
  const { data } = await http.post<ApiResponse<PreinscriptionResume>>('/parent/preinscriptions', payload)
  return data.data
}

export interface PreinscriptionDetail extends PreinscriptionResume {
  donnees_eleve: EleveProposePayload
  donnees_tuteurs: TuteurPayload[]
  note_admin: string | null
  mode_versement: ModePaiement | null
  reference_externe: string | null
}

/** Détail d'une préinscription du compte connecté — pour préremplir son formulaire de modification tant qu'elle est en attente. */
export async function fetchMaPreinscription(id: number): Promise<PreinscriptionDetail> {
  const { data } = await http.get<ApiResponse<PreinscriptionDetail>>(`/parent/preinscriptions/${id}`)
  return data.data
}

/** Corrige une préinscription déjà déposée — le seul geste possible tant qu'elle est en attente : en redéposer une nouvelle est refusé côté API. */
export async function modifierPreinscription(
  id: number,
  payload: Omit<PreinscriptionPayload, 'type' | 'eleve_id' | 'school_id'>,
): Promise<PreinscriptionResume> {
  const { data } = await http.put<ApiResponse<PreinscriptionResume>>(`/parent/preinscriptions/${id}`, payload)
  return data.data
}

export interface EcoleDisponible {
  id: number
  name: string
  type: 'maternelle' | 'primaire' | 'secondaire'
}

export async function fetchEcolesDisponibles(): Promise<EcoleDisponible[]> {
  const { data } = await http.get<ApiResponse<EcoleDisponible[]>>('/parent/ecoles-disponibles')
  return data.data
}

export async function fetchClassesDisponibles(schoolId: number): Promise<{ id: number; nom: string }[]> {
  const { data } = await http.get<ApiResponse<{ id: number; nom: string }[]>>(`/parent/ecoles/${schoolId}/classes`)
  return data.data
}
