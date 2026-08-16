import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

/** Les montants circulent en francs CFA entiers : la devise n'a pas de subdivision. */
export type ModePaiement = 'especes' | 'mobile_money' | 'virement' | 'cheque' | 'depot_bancaire'

export const MODES: { valeur: ModePaiement; libelle: string }[] = [
  { valeur: 'especes', libelle: 'Espèces' },
  { valeur: 'mobile_money', libelle: 'Mobile Money' },
  { valeur: 'virement', libelle: 'Virement' },
  { valeur: 'cheque', libelle: 'Chèque' },
  { valeur: 'depot_bancaire', libelle: 'Dépôt bancaire' },
]

export type StatutPaiement = 'impaye' | 'partiel' | 'solde' | 'avance' | 'sans_frais'

// ---------------------------------------------------------------- Scolarité

export interface DossierScolarite {
  id: number
  eleve: {
    id: number
    matricule: string | null
    nom_complet: string
    classe: string | null
    classe_id: number | null
    contact?: string | null
  }
  montant_scolarite: number
  remise: number
  report_dette: number
  frais_annexes?: { id: number; libelle: string; montant: number }[]
  total_du: number
  total_paye: number
  reste_a_payer: number
  avance: number
  statut_paiement: StatutPaiement
  taux_recouvrement: number
  versements?: {
    id: number
    numero_recu: string
    date_versement: string
    montant: number
    mode: ModePaiement
    annule: boolean
  }[]
  observation: string | null
}

export interface TotauxScolarite {
  effectif: number
  attendu: number
  recouvre: number
  reste: number
  avances: number
  taux_recouvrement: number
  insolvables: number
}

export async function fetchSituation(params: {
  classe_id?: number | null
  statut?: string | null
}): Promise<{ dossiers: DossierScolarite[]; totaux: TotauxScolarite }> {
  const { data } = await http.get<ApiResponse<{ dossiers: DossierScolarite[]; totaux: TotauxScolarite }>>(
    '/scolarite/situation',
    { params },
  )
  return data.data
}

export async function fetchDossier(eleveId: number): Promise<DossierScolarite> {
  const { data } = await http.get<ApiResponse<DossierScolarite>>(`/eleves/${eleveId}/scolarite`)
  return data.data
}

export async function encaisser(
  dossierId: number,
  payload: { montant: number; mode: ModePaiement; date_versement?: string; reference_externe?: string; note?: string },
): Promise<{ versement_id: number; numero_recu: string }> {
  const { data } = await http.post<ApiResponse<{ versement_id: number; numero_recu: string }>>(
    `/scolarite/dossiers/${dossierId}/versements`,
    payload,
  )
  return data.data
}

export async function annulerVersement(versementId: number, motif: string): Promise<void> {
  await http.post(`/versements/${versementId}/annuler`, { motif })
}

// ---------------------------------------------------------------- Dépenses

export interface Depense {
  id: number
  date_depense: string
  libelle: string
  montant: number
  mode: ModePaiement
  statut: 'engagee' | 'payee' | 'annulee'
  beneficiaire: string | null
  reference_facture: string | null
  responsable: string | null
  compte: { id: number; code: string; libelle: string } | null
  justificatif_url: string | null
  saisi_par: string | null
  motif_annulation: string | null
}

export interface CompteComptable {
  id: number
  code: string
  libelle: string
  classe: number
  sens: 'debit' | 'credit'
}

export async function fetchDepenses(params: {
  du?: string | null
  au?: string | null
  statut?: string | null
}): Promise<{
  depenses: Depense[]
  par_compte: { code: string; libelle: string; nombre: number; montant: number }[]
  totaux: { nombre: number; engage: number; paye: number; total: number; annule: number }
}> {
  const { data } = await http.get<ApiResponse<never>>('/depenses', { params })
  return data.data as never
}

export async function fetchComptes(): Promise<CompteComptable[]> {
  const { data } = await http.get<ApiResponse<CompteComptable[]>>('/comptes-comptables')
  return data.data
}

/** `FormData` et non JSON : la dépense peut porter son justificatif scanné. */
export async function creerDepense(champs: Record<string, string | number | Blob | undefined>): Promise<Depense> {
  const formulaire = new FormData()
  Object.entries(champs).forEach(([cle, valeur]) => {
    if (valeur !== undefined && valeur !== '') formulaire.append(cle, valeur as string | Blob)
  })

  const { data } = await http.post<ApiResponse<Depense>>('/depenses', formulaire, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function annulerDepense(id: number, motif: string): Promise<void> {
  await http.post(`/depenses/${id}/annuler`, { motif })
}

export async function payerDepense(id: number, mode?: ModePaiement): Promise<void> {
  await http.post(`/depenses/${id}/payer`, mode ? { mode } : {})
}

// -------------------------------------------------------------------- Paie

export interface BulletinPaie {
  id: number
  numero: string
  personnel: { id: number; nom_complet: string; matricule: string | null; fonction: string | null }
  periode: string
  jours_ouvrables: number
  jours_travailles: number
  salaire_brut: number
  net_taxable: number
  charges_salariales: number
  charges_patronales: number
  total_deductions: number
  net_a_payer: number
  cout_employeur: number
  statut: 'brouillon' | 'valide' | 'paye'
  mode_paiement: ModePaiement | null
  date_paiement: string | null
  emarge: boolean
}

export interface TotauxPaie {
  effectif: number
  brut: number
  charges_salariales: number
  charges_patronales: number
  net_a_payer: number
  cout_employeur: number
  regles: number
  emarges: number
}

export async function fetchPaie(params: { annee: number; mois: number }): Promise<{
  periode: { annee: number; mois: number }
  totaux: TotauxPaie
  bulletins: BulletinPaie[]
}> {
  const { data } = await http.get<ApiResponse<never>>('/paie', { params })
  return data.data as never
}

export async function preparerPaie(
  params: { annee: number; mois: number },
  payload: { jours_ouvrables?: number; jours_travailles?: number },
): Promise<{ prepares: number; ignores: string[] }> {
  const { data } = await http.post<ApiResponse<{ prepares: number; ignores: string[] }>>(
    '/paie/preparer',
    payload,
    { params },
  )
  return data.data
}

export async function arreterBulletin(id: number): Promise<void> {
  await http.post(`/paie/bulletins/${id}/arreter`)
}

export async function arreterBulletins(ids: number[]): Promise<{ arretes: number }> {
  const { data } = await http.post<ApiResponse<{ arretes: number }>>('/paie/bulletins/arreter-lot', { ids })
  return data.data
}

export async function payerBulletin(id: number, mode: ModePaiement, date_paiement?: string): Promise<void> {
  await http.post(`/paie/bulletins/${id}/payer`, { mode, date_paiement })
}

export async function payerBulletins(
  ids: number[],
  mode: ModePaiement,
  date_paiement?: string,
): Promise<{ regles: number }> {
  const { data } = await http.post<ApiResponse<{ regles: number }>>('/paie/bulletins/payer-lot', {
    ids,
    mode,
    date_paiement,
  })
  return data.data
}

export async function emargerBulletin(id: number, reference?: string): Promise<void> {
  await http.post(`/paie/bulletins/${id}/emarger`, { reference })
}

// ----------------------------------------------------------- Rémunérations

/** Les six gains du bulletin, dans leur ordre d'affichage. */
export const GAINS = [
  { champ: 'salaire_base', libelle: 'Salaire de base', exonere: false },
  { champ: 'prime_anciennete', libelle: 'Ancienneté', exonere: false },
  { champ: 'prime_communication', libelle: 'Communication', exonere: true },
  { champ: 'prime_transport', libelle: 'Transport', exonere: true },
  { champ: 'prime_recherche', libelle: 'Recherche & leçon', exonere: false },
  { champ: 'prime_performance', libelle: 'Performance', exonere: false },
] as const

export type ChampGain = (typeof GAINS)[number]['champ']

export interface Remuneration extends Record<ChampGain, number> {
  id: number
  date_effet: string
  categorie: string | null
  brut: number
  base_taxable: number
  charges_salariales: number
  charges_patronales: number
  net: number
  cout_employeur: number
}

export interface LigneRemuneration {
  id: number
  nom_complet: string
  matricule: string | null
  fonction: string | null
  statut: string
  remuneration: Remuneration | null
}

export interface TotauxRemunerations {
  effectif: number
  definies: number
  sans_remuneration: number
  masse_brute: number
  cout_employeur: number
  net_mensuel: number
}

export interface Simulation {
  brut: number
  base_taxable: number
  charges_salariales: number
  charges_patronales: number
  net_avant_deductions: number
  cout_employeur: number
  retenues: {
    libelle: string
    base: number
    taux_salarial: number | null
    taux_patronal: number | null
    montant_salarial: number
    montant_patronal: number
  }[]
}

export async function fetchRemunerations(): Promise<{
  personnels: LigneRemuneration[]
  totaux: TotauxRemunerations
}> {
  const { data } = await http.get<ApiResponse<never>>('/remunerations')
  return data.data as never
}

export async function fetchHistoriqueRemunerations(personnelId: number): Promise<{
  personnel: { id: number; nom_complet: string }
  historique: Remuneration[]
}> {
  const { data } = await http.get<ApiResponse<never>>(`/remunerations/${personnelId}/historique`)
  return data.data as never
}

export async function enregistrerRemuneration(
  personnelId: number,
  payload: Partial<Record<ChampGain, number>> & { date_effet: string; categorie?: string },
): Promise<Remuneration> {
  const { data } = await http.post<ApiResponse<Remuneration>>(`/remunerations/${personnelId}`, payload)
  return data.data
}

/**
 * Applique une même rémunération à plusieurs agents — copiée d'un collègue
 * (`source_personnel_id`) ou saisie directement.
 */
export async function appliquerRemuneration(payload: {
  personnel_ids: number[]
  date_effet: string
  source_personnel_id?: number
  categorie?: string
} & Partial<Record<ChampGain, number>>): Promise<{ appliquee: number; ignores: number }> {
  const { data } = await http.post<ApiResponse<{ appliquee: number; ignores: number }>>(
    '/remunerations/appliquer',
    payload,
  )
  return data.data
}

/** Applique le barème sans rien enregistrer, pour l'aperçu du net. */
export async function simulerRemuneration(gains: Partial<Record<ChampGain, number>>): Promise<Simulation> {
  const { data } = await http.post<ApiResponse<Simulation>>('/remunerations/simuler', gains)
  return data.data
}

// ------------------------------------------------------------------ Tarifs

export interface Tarifs {
  annee_scolaire: { id: number; libelle: string }
  tarif_par_defaut: number | null
  classes: { id: number; nom: string; montant: number | null; dossiers_ouverts: number }[]
  frais_annexes: { id: number; libelle: string; montant: number; obligatoire: boolean; is_active: boolean }[]
}

export async function fetchTarifs(): Promise<Tarifs> {
  const { data } = await http.get<ApiResponse<Tarifs>>('/tarifs')
  return data.data
}

/** `classe_id` nul = tarif par défaut de l'établissement. */
export async function definirTarif(classeId: number | null, montant: number): Promise<void> {
  await http.post('/tarifs', { classe_id: classeId, montant })
}

export async function supprimerTarif(classeId: number): Promise<void> {
  await http.delete(`/tarifs/classes/${classeId}`)
}

export async function creerFraisAnnexe(payload: {
  libelle: string
  montant: number
  obligatoire: boolean
}): Promise<void> {
  await http.post('/tarifs/frais-annexes', payload)
}

export async function modifierFraisAnnexe(
  id: number,
  payload: Partial<{ libelle: string; montant: number; obligatoire: boolean; is_active: boolean }>,
): Promise<void> {
  await http.put(`/tarifs/frais-annexes/${id}`, payload)
}

export async function desactiverFraisAnnexe(id: number): Promise<void> {
  await http.delete(`/tarifs/frais-annexes/${id}`)
}

// ---------------------------------------------------------------- Rapports

export interface LigneCompte {
  code: string
  libelle: string
  montant: number
}

export interface Resultat {
  periode: { du: string | null; au: string | null }
  produits: { lignes: LigneCompte[]; total: number }
  charges: { lignes: LigneCompte[]; total: number }
  resultat: number
  taux_charges: number
}

export interface Tresorerie {
  comptes: { code: string; libelle: string; debit: number; credit: number; solde: number }[]
  disponible: number
}

export interface Balance {
  lignes: { code: string; libelle: string; classe: number; debit: number; credit: number; solde: number }[]
  total_debit: number
  total_credit: number
  equilibre: boolean
}

export interface TableauDeBord {
  scolarite: TotauxScolarite
  paie: { bulletins: number; masse_brute: number; cout_employeur: number; a_regler: number }
  resultat: { produits: number; charges: number; solde: number }
  tresorerie: number
}

type Periode = { du?: string | null; au?: string | null }

export async function fetchTableauDeBord(): Promise<TableauDeBord> {
  const { data } = await http.get<ApiResponse<TableauDeBord>>('/rapports/tableau-de-bord')
  return data.data
}

export async function fetchResultat(params: Periode): Promise<Resultat> {
  const { data } = await http.get<ApiResponse<Resultat>>('/rapports/resultat', { params })
  return data.data
}

export async function fetchTresorerie(params: Periode): Promise<Tresorerie> {
  const { data } = await http.get<ApiResponse<Tresorerie>>('/rapports/tresorerie', { params })
  return data.data
}

export async function fetchBalance(params: Periode): Promise<Balance> {
  const { data } = await http.get<ApiResponse<Balance>>('/rapports/balance', { params })
  return data.data
}

/** Séparateur d'unités de mille, comme sur les documents imprimés. */
export function francs(montant: number | null | undefined): string {
  return `${(montant ?? 0).toLocaleString('fr-FR').replace(/ | /g, ' ')} F`
}
