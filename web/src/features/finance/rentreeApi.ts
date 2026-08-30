import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import type { RubriqueBudgetFonctionnement } from '@/features/finance/api'

export interface LigneBudgetFonctionnement {
  rubrique: RubriqueBudgetFonctionnement
  montant_percu: number
  montant_depense: number
  reste: number
}

export interface AssuranceScolaire {
  id: number
  libelle: string
  effectif: number
  nom_assureur: string | null
  numero_police: string | null
}

export interface AssuranceScolairePayload {
  annee_scolaire_id: number
  libelle: string
  effectif: number
  nom_assureur?: string | null
  numero_police?: string | null
}

export interface ConseilEcole {
  id?: number
  existe: boolean
  date_ag_elective: string | null
  duree_mandat: string | null
  fin_mandat: number | null
  president_nom: string | null
  president_fonction: string | null
  president_telephone: string | null
  statut_projet_ecole: string | null
  observations: string | null
}

export interface Apee {
  id?: number
  legalisee: boolean
  date_legalisation: string | null
  numero_recepisse: string | null
  banque: string | null
  numero_compte: string | null
  president_nom: string | null
  president_fonction: string | null
  president_telephone: string | null
  date_ag_elective: string | null
  fin_mandat: number | null
  taux_par_eleve: number | null
  montant_percu: number
  montant_depense: number
  montant_restant: number
  realisations: string | null
}

export async function fetchBudgetFonctionnement(anneeScolaireId?: number): Promise<LigneBudgetFonctionnement[]> {
  const { data } = await http.get<ApiResponse<LigneBudgetFonctionnement[]>>('/budget-fonctionnement', {
    params: anneeScolaireId ? { annee_scolaire_id: anneeScolaireId } : undefined,
  })
  return data.data
}

export async function definirMontantPercu(
  rubrique: RubriqueBudgetFonctionnement,
  anneeScolaireId: number,
  montantPercu: number,
  observations?: string,
): Promise<LigneBudgetFonctionnement> {
  const { data } = await http.put<ApiResponse<LigneBudgetFonctionnement>>(`/budget-fonctionnement/${rubrique}`, {
    annee_scolaire_id: anneeScolaireId,
    montant_percu: montantPercu,
    observations,
  })
  return data.data
}

export async function fetchAssurancesScolaires(anneeScolaireId?: number): Promise<AssuranceScolaire[]> {
  const { data } = await http.get<ApiResponse<AssuranceScolaire[]>>('/assurances-scolaires', {
    params: anneeScolaireId ? { annee_scolaire_id: anneeScolaireId } : undefined,
  })
  return data.data
}

export async function creerAssuranceScolaire(payload: AssuranceScolairePayload): Promise<AssuranceScolaire> {
  const { data } = await http.post<ApiResponse<AssuranceScolaire>>('/assurances-scolaires', payload)
  return data.data
}

export async function modifierAssuranceScolaire(id: number, payload: Partial<AssuranceScolairePayload>): Promise<AssuranceScolaire> {
  const { data } = await http.put<ApiResponse<AssuranceScolaire>>(`/assurances-scolaires/${id}`, payload)
  return data.data
}

export async function supprimerAssuranceScolaire(id: number): Promise<void> {
  await http.delete(`/assurances-scolaires/${id}`)
}

export async function fetchConseilEcole(anneeScolaireId?: number): Promise<ConseilEcole> {
  const { data } = await http.get<ApiResponse<ConseilEcole>>('/conseil-ecole', {
    params: anneeScolaireId ? { annee_scolaire_id: anneeScolaireId } : undefined,
  })
  return data.data
}

export async function definirConseilEcole(anneeScolaireId: number, payload: Partial<ConseilEcole>): Promise<ConseilEcole> {
  const { data } = await http.put<ApiResponse<ConseilEcole>>('/conseil-ecole', {
    ...payload,
    annee_scolaire_id: anneeScolaireId,
  })
  return data.data
}

export async function fetchApee(anneeScolaireId?: number): Promise<Apee> {
  const { data } = await http.get<ApiResponse<Apee>>('/apee', {
    params: anneeScolaireId ? { annee_scolaire_id: anneeScolaireId } : undefined,
  })
  return data.data
}

export async function definirApee(anneeScolaireId: number, payload: Partial<Apee>): Promise<Apee> {
  const { data } = await http.put<ApiResponse<Apee>>('/apee', {
    ...payload,
    annee_scolaire_id: anneeScolaireId,
  })
  return data.data
}
