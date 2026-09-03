import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

// -------------------------------------------------------------------- Moi

export interface MoiTuteur {
  nom_complet: string
  telephone: string | null
  lien_parente: string | null
}

export interface MoiDossier {
  id: number
  matricule: string | null
  nom_complet: string
  sexe: 'M' | 'F'
  date_naissance: string | null
  lieu_naissance: string | null
  nationalite: string | null
  adresse: string | null
  photo_url: string | null
  redoublant: boolean
  statut: string
  classe: { id: number; nom: string; sous_systeme: string | null } | null
  school: { id: number; name: string; type: string } | null
  sante: { groupe_sanguin: string | null; situation_sanitaire: string | null; aptitude: 'apte' | 'inapte'; allergies: string | null }
  tuteurs: MoiTuteur[]
}

export async function fetchMoi(): Promise<MoiDossier> {
  const { data } = await http.get<ApiResponse<MoiDossier>>('/eleve/moi')
  return data.data
}

// ------------------------------------------------------------------- Notes

export interface NoteSequence {
  sequence_id: number
  libelle: string
  valeur: number | null
  total?: number | null
  appreciation?: { id: number; label_fr: string; emoji: string; couleur: string } | null
}

export interface LigneMatiere {
  matiere_id: number
  matiere: string
  abreviation: string | null
  coefficient: number
  notes: NoteSequence[]
  moyenne: number | null
  rang: number | null
}

export interface LigneCompetence {
  competence_id: number
  competence: string
  abreviation: string | null
  mode: 'note' | 'appreciation'
  bareme: number | null
  notes: NoteSequence[]
  moyenne: number | null
  rang: number | null
}

export interface NotesEleve {
  eleve: { id: number; nom_complet: string }
  trimestre: { id: number; libelle: string }
  sequences: { id: number; libelle: string }[]
  matieres?: LigneMatiere[]
  competences?: LigneCompetence[]
  moyenne_generale: number | null
  rang_general: number | null
}

export async function fetchNotes(trimestreId?: number): Promise<NotesEleve> {
  const { data } = await http.get<ApiResponse<NotesEleve>>('/eleve/notes', { params: trimestreId ? { trimestre_id: trimestreId } : undefined })
  return data.data
}

// -------------------------------------------------------------- Emploi du temps

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

export async function fetchEmploiDuTemps(): Promise<CreneauEmploiDuTemps[]> {
  const { data } = await http.get<ApiResponse<CreneauEmploiDuTemps[]>>('/eleve/emploi-du-temps')
  return data.data
}

// -------------------------------------------------------------------- Assiduité

export interface AbsenceEleve {
  date: string
  statut: 'absent' | 'retard'
  motif: string | null
  justifie: boolean
  remarque: string | null
}

export async function fetchAbsences(): Promise<AbsenceEleve[]> {
  const { data } = await http.get<ApiResponse<AbsenceEleve[]>>('/eleve/absences')
  return data.data
}

export interface JourAssiduite {
  date: string
  statut: 'present' | 'absent_justifiee' | 'absent_non_justifiee'
}

export async function fetchAssiduite(): Promise<JourAssiduite[]> {
  const { data } = await http.get<ApiResponse<JourAssiduite[]>>('/eleve/assiduite')
  return data.data
}

// ------------------------------------------------------------------ Infirmerie

export interface VisiteInfirmerieEleve {
  id: number
  date_visite: string
  raison: string | null
  malaises: { id: number; label_fr: string; label_en: string }[]
  soins_prodiges: string | null
  observations: string | null
  enregistre_par: string | null
}

export async function fetchVisitesInfirmerie(): Promise<VisiteInfirmerieEleve[]> {
  const { data } = await http.get<ApiResponse<VisiteInfirmerieEleve[]>>('/eleve/visites-infirmerie')
  return data.data
}

// ------------------------------------------------------------------ Discipline

export interface SanctionEleve {
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

export interface DossierDisciplinaireEleve {
  total_sanctions: number
  sanctions_en_cours: number
  est_exclu: boolean
  motif_exclusion: string | null
  date_exclusion: string | null
  sanctions: SanctionEleve[]
}

export async function fetchSanctions(): Promise<DossierDisciplinaireEleve> {
  const { data } = await http.get<ApiResponse<DossierDisciplinaireEleve>>('/eleve/sanctions')
  return data.data
}
