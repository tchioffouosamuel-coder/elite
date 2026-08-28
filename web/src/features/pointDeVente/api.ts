import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import type { ModePaiement } from '@/features/finance/api'

/** Article tel que le comptoir le voit : ce qu'il reste, et à quel prix. */
export interface ArticleComptoir {
  id: number
  nom: string
  code_barre: string | null
  categorie: string
  quantite: number
  prix_vente: number | null
  valeur_unitaire: number | null
  school?: { id: number; name: string } | null
}

export interface LigneVente {
  id: number
  article_id: number | null
  libelle: string
  quantite: number
  prix_unitaire: number
  cout_unitaire: number | null
  total: number
}

export interface VenteFourniture {
  id: number
  numero_facture: string
  date_vente: string
  montant: number
  cout: number
  marge: number
  mode: ModePaiement
  client: string | null
  note: string | null
  annule: boolean
  motif_annulation: string | null
  eleve: { id: number; nom_complet: string; matricule: string | null } | null
  vendeur: string | null
  school?: { id: number; name: string } | null
  lignes: LigneVente[]
}

export interface TotauxVentes {
  effectif: number
  montant: number
  cout: number
  marge: number
}

export interface EntreeStock {
  id: number
  date_entree: string
  quantite: number
  cout_unitaire: number
  cout_total: number
  fournisseur: string | null
  reference: string | null
  note: string | null
  enregistre_par: string | null
  article: { id: number; nom: string; code_barre: string | null; quantite: number } | null
  school?: { id: number; name: string } | null
}

export interface TotauxEntrees {
  effectif: number
  quantite: number
  cout: number
}

/** Une ligne du panier, avant validation de la facture. */
export interface LigneVentePayload {
  article_id: number
  quantite: number
  prix_unitaire?: number
}

export interface VentePayload {
  lignes: LigneVentePayload[]
  mode?: ModePaiement
  eleve_id?: number | null
  client?: string | null
  note?: string | null
  date_vente?: string
}

export interface EntreeStockPayload {
  article_id: number
  quantite: number
  cout_unitaire: number
  fournisseur?: string | null
  reference?: string | null
  note?: string | null
  date_entree?: string
  comptabiliser?: boolean
}

export interface StatsVendeur {
  ventes: {
    jour: { effectif: number; montant: number }
    mois: { effectif: number; montant: number }
  }
  stock: {
    effectif_articles: number
    quantite_totale: number
    valeur_totale: number
  }
}

/** Stats d'accueil du vendeur : ses ventes (jour/mois) et le stock vendable — jamais les effectifs élèves/personnel. */
export async function fetchStatsVendeur(): Promise<StatsVendeur> {
  const { data } = await http.get<ApiResponse<StatsVendeur>>('/point-de-vente/stats-vendeur')
  return data.data
}

export async function fetchCatalogue(search?: string): Promise<ArticleComptoir[]> {
  const { data } = await http.get<ApiResponse<ArticleComptoir[]>>('/point-de-vente/catalogue', {
    params: search ? { search } : undefined,
  })
  return data.data
}

/**
 * Article désigné par une étiquette scannée. L'API rend 404 sur un code
 * inconnu : l'appelant traite ce cas comme une information à afficher au
 * comptoir, pas comme une panne.
 */
export async function fetchArticleParCodeBarre(code: string): Promise<ArticleComptoir> {
  const { data } = await http.get<ApiResponse<ArticleComptoir>>(`/point-de-vente/articles/${encodeURIComponent(code)}`)
  return data.data
}

export async function fetchVentes(params?: {
  du?: string
  au?: string
  eleve_id?: number
  annulees?: boolean
}): Promise<{ ventes: VenteFourniture[]; totaux: TotauxVentes }> {
  const { data } = await http.get<ApiResponse<{ ventes: VenteFourniture[]; totaux: TotauxVentes }>>(
    '/point-de-vente/ventes',
    { params },
  )
  return data.data
}

export async function enregistrerVente(payload: VentePayload): Promise<VenteFourniture> {
  const { data } = await http.post<ApiResponse<VenteFourniture>>('/point-de-vente/ventes', payload)
  return data.data
}

export async function annulerVente(id: number, motif: string): Promise<VenteFourniture> {
  const { data } = await http.post<ApiResponse<VenteFourniture>>(`/point-de-vente/ventes/${id}/annuler`, { motif })
  return data.data
}

export async function fetchEntrees(params?: {
  du?: string
  au?: string
  article_id?: number
}): Promise<{ entrees: EntreeStock[]; totaux: TotauxEntrees }> {
  const { data } = await http.get<ApiResponse<{ entrees: EntreeStock[]; totaux: TotauxEntrees }>>(
    '/point-de-vente/entrees',
    { params },
  )
  return data.data
}

export async function enregistrerEntree(payload: EntreeStockPayload): Promise<EntreeStock> {
  const { data } = await http.post<ApiResponse<EntreeStock>>('/point-de-vente/entrees', payload)
  return data.data
}
