import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface DashboardStats {
  annee_scolaire_active: string | null
  effectifs: { eleves: number; personnel: number; enseignants: number; classes: number }
  repartition_genre: { garcons: number; filles: number }
  top_classes: { classe: string; effectif: number }[]
  indicateurs: { taux_filles: number; eleves_par_classe_moyenne: number }
  activite_recente: { type: string; libelle: string; date: string }[]
}

export async function fetchDashboardStats(): Promise<DashboardStats> {
  const { data } = await http.get<ApiResponse<DashboardStats>>('/dashboard')
  return data.data
}
