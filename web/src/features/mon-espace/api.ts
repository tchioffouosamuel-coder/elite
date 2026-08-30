import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import type { PlafondAvance, StatutAvance, StatutBudget, StatutDemandeAvance } from '@/features/finance/api'

export interface MonAvance {
  id: number
  montant: number
  nombre_mois: number | null
  mensualite: number | null
  mois_debut_remboursement: string | null
  date_avance: string
  motif: string | null
  montant_rembourse: number
  solde: number
  statut: StatutAvance
}

export interface MaDemandeAvance {
  id: number
  montant: number
  nombre_mois: number
  mensualite: number
  mois_debut_remboursement: string | null
  motif: string | null
  statut: StatutDemandeAvance
  motif_rejet: string | null
  created_at: string
}

export interface MonEspaceAvances {
  /** Salaire brut en cours et mensualité maximale : la borne du calendrier de remboursement. */
  plafond: PlafondAvance
  avances: MonAvance[]
  demandes: MaDemandeAvance[]
}

export async function fetchMesAvances(): Promise<MonEspaceAvances> {
  const { data } = await http.get<ApiResponse<MonEspaceAvances>>('/mon-espace/avances')
  return data.data
}

export async function soumettreDemandeAvance(payload: {
  montant: number
  mensualite: number
  mois_debut_remboursement?: string | null
  motif?: string | null
}): Promise<MaDemandeAvance> {
  const { data } = await http.post<ApiResponse<MaDemandeAvance>>('/mon-espace/avances/demandes', payload)
  return data.data
}

// ---------------------------------------------------------------- Budgets

export interface MonBudget {
  id: number
  libelle: string
  montant_alloue: number
  montant_depense: number
  solde: number
  statut: StatutBudget
  date_allocation: string
  note_gestion: string | null
}

export async function fetchMesBudgets(): Promise<{ budgets: MonBudget[] }> {
  const { data } = await http.get<ApiResponse<{ budgets: MonBudget[] }>>('/mon-espace/budgets')
  return data.data
}

export async function modifierNoteGestionMonBudget(id: number, note_gestion: string): Promise<{ note_gestion: string }> {
  const { data } = await http.put<ApiResponse<{ note_gestion: string }>>(`/mon-espace/budgets/${id}/note-gestion`, {
    note_gestion,
  })
  return data.data
}
