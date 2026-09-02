import { http } from '@/shared/lib/http'
import type { ApiResponse, Pagination } from '@/shared/types/api'

export interface ActiviteLog {
  type: string
  libelle: string
  date: string
}

export interface DashboardStatsEcole {
  scope: 'ecole'
  annee_scolaire_active: string | null
  effectifs: { eleves: number; personnel: number; enseignants: number; classes: number }
  repartition_genre: { garcons: number; filles: number }
  top_classes: { classe: string; effectif: number }[]
  indicateurs: { taux_filles: number; eleves_par_classe_moyenne: number }
  activite_recente: ActiviteLog[]
}

/** Enseignant (ou titulaire de primaire/maternelle) : le tableau de bord se limite à ses classes. */
export interface DashboardStatsClasse {
  scope: 'classe'
  classe: { id: number; nom: string }
  annee_scolaire_active: string | null
  effectifs: { eleves: number; matieres: number; classes: number }
  repartition_genre: { garcons: number; filles: number }
  indicateurs: {
    taux_filles: number
    /** % d'élèves notés sur la séquence active, moyenné sur mes affectations — `null` hors séquence active ou sans affectation notée. */
    taux_remplissage_notes: number | null
    /** % de leçons traitées, moyenné sur mes affectations — `null` sans affectation. */
    taux_progression: number | null
  }
  activite_recente: ActiviteLog[]
}

export type DashboardStats = DashboardStatsEcole | DashboardStatsClasse

export async function fetchDashboardStats(): Promise<DashboardStats> {
  const { data } = await http.get<ApiResponse<DashboardStats>>('/dashboard')
  return data.data
}

export interface CreneauPilotage {
  emploi_du_temps_id: number
  classe: string
  ecole: string | null
  matiere: string | null
  salle: string | null
  enseignant: string | null
  heure_debut: string
  heure_fin: string
  appel_fait: boolean
}

export interface ClasseSansEnseignant {
  classe: string
  matiere: string | null
  ecole: string
}

export interface Pilotage {
  genere_le: string
  cours_en_cours: CreneauPilotage[]
  cours_a_venir: CreneauPilotage[]
  appels_en_retard: CreneauPilotage[]
  classes_sans_enseignant: ClasseSansEnseignant[]
  couverture: {
    lecons: number
    traitees: number
    taux: number
    classes_en_retard: { classe: string; niveau: string | null; taux: number }[]
  }
}

export async function fetchPilotage(): Promise<Pilotage> {
  const { data } = await http.get<ApiResponse<Pilotage>>('/dashboard/pilotage')
  return data.data
}

/** Journal complet, paginé — derrière le « Voir plus » de la carte Activité récente. */
export async function fetchActiviteRecente(page: number, perPage = 25): Promise<{ items: ActiviteLog[]; pagination: Pagination }> {
  const { data } = await http.get<ApiResponse<ActiviteLog[]>>('/dashboard/activite', { params: { page, per_page: perPage } })
  return { items: data.data, pagination: data.meta!.pagination }
}
