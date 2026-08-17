import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export type CategorieArticle = 'mobilier' | 'informatique' | 'pedagogique' | 'sport' | 'autre'
export type EtatArticle = 'bon' | 'moyen' | 'mauvais' | 'hors_service'

export interface ArticleInventaire {
  id: number
  nom: string
  categorie: CategorieArticle
  quantite: number
  etat: EtatArticle
  localisation: string | null
  valeur_unitaire: number | null
  valeur_totale: number
  date_acquisition: string | null
  notes: string | null
}

export interface ArticleInventairePayload {
  nom: string
  categorie: CategorieArticle
  quantite: number
  etat: EtatArticle
  localisation?: string | null
  valeur_unitaire?: number | null
  date_acquisition?: string | null
  notes?: string | null
}

export interface StatsInventaire {
  effectif_articles: number
  quantite_totale: number
  valeur_totale: number
  par_etat: Partial<Record<EtatArticle, number>>
}

export async function fetchInventaire(params?: { categorie?: CategorieArticle; etat?: EtatArticle; search?: string }): Promise<{
  articles: ArticleInventaire[]
  stats: StatsInventaire
}> {
  const { data } = await http.get<ApiResponse<{ articles: ArticleInventaire[]; stats: StatsInventaire }>>('/inventaire', { params })
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
