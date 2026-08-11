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

export interface FeuilleJournee {
  seance: { id: number; date: string; heure_debut: string; heure_fin: string; statut: string }
  lecons: {
    id: number
    titre: string
    chemin: string
    sequence: string | null
    faite_aujourdhui: boolean
    deja_traitee: boolean
  }[]
  appel: LigneAppel[]
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
  payload: { date?: string; lecons: number[]; appel: { eleve_id: number; statut: string; motif: MotifAbsence | null }[] },
): Promise<FeuilleJournee> {
  const { data } = await http.post<ApiResponse<FeuilleJournee>>(`/ma-journee/${classeMatiereId}`, payload)
  return data.data
}
