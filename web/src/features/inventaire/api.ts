import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export type CategorieArticle = 'mobilier' | 'informatique' | 'pedagogique' | 'sport' | 'medical' | 'autre'
export type EtatArticle = 'bon' | 'moyen' | 'mauvais' | 'hors_service'

export interface ArticleInventaire {
  id: number
  nom: string
  /** EAN-13 de l'étiquette collée sur l'article ; `null` tant qu'il n'a pas été généré. */
  code_barre: string | null
  categorie: CategorieArticle
  quantite: number
  etat: EtatArticle
  localisation: string | null
  valeur_unitaire: number | null
  valeur_totale: number
  /** Tarif au comptoir. Renseigné, il met l'article en vente au point de vente. */
  prix_vente: number | null
  valeur_vente: number | null
  date_acquisition: string | null
  notes: string | null
  school?: { id: number; name: string; code: string; type: string } | null
}

export interface ArticleInventairePayload {
  nom: string
  categorie: CategorieArticle
  quantite: number
  etat: EtatArticle
  localisation?: string | null
  valeur_unitaire?: number | null
  prix_vente?: number | null
  date_acquisition?: string | null
  notes?: string | null
  school_id?: number | null
  /**
   * Article partagé par tout le complexe : aucune école propriétaire, un seul
   * stock où les trois puisent. Exclusif de `school_id`.
   */
  toutes_ecoles?: boolean
}

export interface StatsInventaire {
  effectif_articles: number
  quantite_totale: number
  valeur_totale: number
  par_etat: Partial<Record<EtatArticle, number>>
}

export async function fetchInventaire(
  params?: { categorie?: CategorieArticle; etat?: EtatArticle; search?: string },
  schoolId?: number,
): Promise<{
  articles: ArticleInventaire[]
  stats: StatsInventaire
}> {
  const { data } = await http.get<ApiResponse<{ articles: ArticleInventaire[]; stats: StatsInventaire }>>('/inventaire', {
    params,
    headers: schoolId ? { 'X-School-Id': String(schoolId) } : undefined,
  })
  return data.data
}

export async function creerArticle(payload: ArticleInventairePayload): Promise<ArticleInventaire> {
  const { data } = await http.post<ApiResponse<ArticleInventaire>>('/inventaire', payload)
  return data.data
}

export async function modifierArticle(id: number, payload: ArticleInventairePayload): Promise<ArticleInventaire> {
  const { data } = await http.put<ApiResponse<ArticleInventaire>>(`/inventaire/${id}`, payload)
  return data.data
}

export async function supprimerArticle(id: number): Promise<void> {
  await http.delete(`/inventaire/${id}`)
}

/**
 * Attribue son code-barres à l'article. Idempotent : un article déjà étiqueté
 * conserve le sien, sans quoi les étiquettes déjà collées deviendraient muettes.
 */
export async function genererCodeBarre(id: number): Promise<ArticleInventaire> {
  const { data } = await http.post<ApiResponse<ArticleInventaire>>(`/inventaire/${id}/code-barre`)
  return data.data
}

/**
 * Ouvre la planche d'étiquettes des articles désignés dans un nouvel onglet.
 *
 * Requête POST : la liste d'identifiants peut être longue et n'a pas sa place
 * dans une URL. On récupère donc le PDF en blob avant de l'afficher, plutôt
 * que de pointer une fenêtre sur l'adresse.
 */
export async function ouvrirEtiquettes(ids: number[], exemplaires = 1): Promise<void> {
  const { data } = await http.post('/inventaire/etiquettes', { ids, exemplaires }, { responseType: 'blob' })
  const url = URL.createObjectURL(new Blob([data as BlobPart], { type: 'application/pdf' }))
  window.open(url, '_blank', 'noopener')
  // Laisse le temps à l'onglet de lire le blob avant de le révoquer.
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}
