import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface DashboardStatsEcole {
  scope: 'ecole'
  annee_scolaire_active: string | null
  effectifs: { eleves: number; personnel: number; enseignants: number; classes: number }
  repartition_genre: { garcons: number; filles: number }
  top_classes: { classe: string; effectif: number }[]
  indicateurs: { taux_filles: number; eleves_par_classe_moyenne: number }
  activite_recente: { type: string; libelle: string; date: string }[]
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
  activite_recente: { type: string; libelle: string; date: string }[]
}

export type DashboardStats = DashboardStatsEcole | DashboardStatsClasse

export async function fetchDashboardStats(): Promise<DashboardStats> {
  const { data } = await http.get<ApiResponse<DashboardStats>>('/dashboard')
  return data.data
}
