import { http } from '@/shared/lib/http'
import type { ApiResponse, Pagination } from '@/shared/types/api'

export type TypeCompte = 'personnel' | 'parent' | 'super_admin' | 'autre'

export interface CompteUtilisateur {
  id: number
  nom: string
  email: string | null
  phone: string | null
  est_actif: boolean
  doit_changer_mot_de_passe: boolean
  type: TypeCompte
  role: string | null
  matricule: string | null
  school: { id: number; name: string } | null
  /** Écoles supplémentaires (compte de direction transverse : « Directrice Primaire et Maternelle », chauffeur/infirmier/vendeur des deux écoles). */
  ecoles_supplementaires: { id: number; name: string }[]
  /** Dernière connexion réussie, d'après le journal d'activité — absente si le compte ne s'est jamais connecté. */
  derniere_connexion: string | null
  cree_le: string | null
}

export interface EntreeActivite {
  id: number
  user_id: number | null
  causer_nom: string
  causer_role: string | null
  action: string
  description: string
  subject_type: string | null
  subject_id: number | null
  ip_address: string | null
  created_at: string
}

export async function fetchComptesUtilisateurs(): Promise<CompteUtilisateur[]> {
  const { data } = await http.get<ApiResponse<CompteUtilisateur[]>>('/comptes-utilisateurs')
  return data.data
}

export async function fetchActiviteCompte(
  compteId: number,
  page = 1,
): Promise<{ items: EntreeActivite[]; pagination: Pagination }> {
  const { data } = await http.get<ApiResponse<EntreeActivite[]>>(`/comptes-utilisateurs/${compteId}/activite`, {
    params: { page },
  })
  return { items: data.data, pagination: data.meta!.pagination! }
}

/** Remplace les écoles supplémentaires du compte (hors école principale) — cf. `CompteController::attribuerEcoles()`. */
export async function attribuerEcolesCompte(compteId: number, schoolIds: number[]): Promise<void> {
  await http.put(`/comptes-utilisateurs/${compteId}/ecoles`, { school_ids: schoolIds })
}

export async function reinitialiserMotDePasseCompte(
  compteId: number,
  nouveauMotDePasse: string,
  confirmation: string,
): Promise<void> {
  await http.post(`/comptes-utilisateurs/${compteId}/reinitialiser-mot-de-passe`, {
    nouveau_mot_de_passe: nouveauMotDePasse,
    nouveau_mot_de_passe_confirmation: confirmation,
  })
}
