import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import type { RubriqueScolarite, StatutPaiement, ModePaiement } from '@/features/finance/api'

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

export interface TuteurEnfant {
  id: number
  nom_complet: string
  telephone: string | null
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
  statut: 'en_attente'
  created_at: string
}

export async function fetchModificationEnAttente(eleveId: number): Promise<ModificationEnfantResume | null> {
  const { data } = await http.get<ApiResponse<ModificationEnfantResume | null>>(`/parent/enfants/${eleveId}/modification`)
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
  telephone?: string
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
