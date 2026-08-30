import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface VisiteAutorite {
  id: number
  date_visite: string
  qualite_autorite: string
  nature_visite: string | null
  objectifs: string | null
  observations: string | null
}

export interface VisiteAutoritePayload {
  annee_scolaire_id: number
  date_visite: string
  qualite_autorite: string
  nature_visite?: string | null
  objectifs?: string | null
  observations?: string | null
}

export type CategorieActivite = 'pedagogique' | 'eps' | 'fenassco'

export interface ActiviteRentree {
  id: number
  categorie: CategorieActivite
  activite: string
  periode: string | null
  objectifs_vises: string | null
  prevues: number | null
  faites: number | null
  taux_realisation: number | null
  taux_affichage: number | null
  observations: string | null
}

export interface ActiviteRentreePayload {
  annee_scolaire_id: number
  categorie: CategorieActivite
  activite: string
  periode?: string | null
  objectifs_vises?: string | null
  prevues?: number | null
  faites?: number | null
  taux_realisation?: number | null
  observations?: string | null
}

export interface VenteDenree {
  id: number
  nature: string
  vendeur_nom: string | null
  dossier_medical_ok: boolean | null
  frais_verses: number
  gestion_frais: string | null
}

export interface VenteDenreePayload {
  annee_scolaire_id: number
  nature: string
  vendeur_nom?: string | null
  dossier_medical_ok?: boolean | null
  frais_verses?: number
  gestion_frais?: string | null
}

export type RubriqueTexteRentree =
  | 'securite_cloture'
  | 'securite_detecteur_metaux'
  | 'securite_controle_armes'
  | 'securite_surveillance_pauses'
  | 'securite_autres_mesures'
  | 'probleme_infrastructure_maternelle'
  | 'doleances'
  | 'problemes_fonctionnement'
  | 'resolutions_conseil_maitres'
  | 'gouvernements_enfants'
  | 'irr'
  | 'evenements_socioculturels'
  | 'fetes_nationales'
  | 'conclusion_generale'

export type TextesRentree = Record<RubriqueTexteRentree, string | null>

export async function fetchVisitesAutorites(anneeScolaireId?: number): Promise<VisiteAutorite[]> {
  const { data } = await http.get<ApiResponse<VisiteAutorite[]>>('/visites-autorites', {
    params: anneeScolaireId ? { annee_scolaire_id: anneeScolaireId } : undefined,
  })
  return data.data
}

export async function creerVisiteAutorite(payload: VisiteAutoritePayload): Promise<VisiteAutorite> {
  const { data } = await http.post<ApiResponse<VisiteAutorite>>('/visites-autorites', payload)
  return data.data
}

export async function modifierVisiteAutorite(id: number, payload: Partial<VisiteAutoritePayload>): Promise<VisiteAutorite> {
  const { data } = await http.put<ApiResponse<VisiteAutorite>>(`/visites-autorites/${id}`, payload)
  return data.data
}

export async function supprimerVisiteAutorite(id: number): Promise<void> {
  await http.delete(`/visites-autorites/${id}`)
}

export async function fetchActivitesRentree(anneeScolaireId?: number, categorie?: CategorieActivite): Promise<ActiviteRentree[]> {
  const { data } = await http.get<ApiResponse<ActiviteRentree[]>>('/activites-rentree', {
    params: { annee_scolaire_id: anneeScolaireId, categorie },
  })
  return data.data
}

export async function creerActiviteRentree(payload: ActiviteRentreePayload): Promise<ActiviteRentree> {
  const { data } = await http.post<ApiResponse<ActiviteRentree>>('/activites-rentree', payload)
  return data.data
}

export async function modifierActiviteRentree(id: number, payload: Partial<ActiviteRentreePayload>): Promise<ActiviteRentree> {
  const { data } = await http.put<ApiResponse<ActiviteRentree>>(`/activites-rentree/${id}`, payload)
  return data.data
}

export async function supprimerActiviteRentree(id: number): Promise<void> {
  await http.delete(`/activites-rentree/${id}`)
}

export async function fetchVentesDenrees(anneeScolaireId?: number): Promise<VenteDenree[]> {
  const { data } = await http.get<ApiResponse<VenteDenree[]>>('/ventes-denrees', {
    params: anneeScolaireId ? { annee_scolaire_id: anneeScolaireId } : undefined,
  })
  return data.data
}

export async function creerVenteDenree(payload: VenteDenreePayload): Promise<VenteDenree> {
  const { data } = await http.post<ApiResponse<VenteDenree>>('/ventes-denrees', payload)
  return data.data
}

export async function modifierVenteDenree(id: number, payload: Partial<VenteDenreePayload>): Promise<VenteDenree> {
  const { data } = await http.put<ApiResponse<VenteDenree>>(`/ventes-denrees/${id}`, payload)
  return data.data
}

export async function supprimerVenteDenree(id: number): Promise<void> {
  await http.delete(`/ventes-denrees/${id}`)
}

export async function fetchTextesRentree(anneeScolaireId?: number): Promise<TextesRentree> {
  const { data } = await http.get<ApiResponse<TextesRentree>>('/rapport-rentree-textes', {
    params: anneeScolaireId ? { annee_scolaire_id: anneeScolaireId } : undefined,
  })
  return data.data
}

export async function definirTexteRentree(
  rubrique: RubriqueTexteRentree,
  anneeScolaireId: number,
  contenu: string,
): Promise<void> {
  await http.put(`/rapport-rentree-textes/${rubrique}`, { annee_scolaire_id: anneeScolaireId, contenu })
}
