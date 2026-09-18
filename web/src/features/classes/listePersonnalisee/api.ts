import { http } from '@/shared/lib/http'
import { ouvrirDocument, telechargerFichier } from '@/shared/lib/download'
import type { ApiResponse } from '@/shared/types/api'

export type ColonneListePersonnalisee =
  | 'numero'
  | 'nom_prenom'
  | 'date_naissance'
  | 'lieu_naissance'
  | 'sexe'
  | 'age'
  | 'statut_solvabilite'
  | 'reste_scolarite_a_payer'
  | 'situation_transport'
  | 'dette_anterieure'
  | 'moyenne'

/** Ordre d'affichage proposé à l'utilisateur — reflète App\Support\ListeClasseColonnes côté API. */
export const COLONNES_LISTE_PERSONNALISEE: ColonneListePersonnalisee[] = [
  'numero',
  'nom_prenom',
  'date_naissance',
  'lieu_naissance',
  'sexe',
  'age',
  'statut_solvabilite',
  'reste_scolarite_a_payer',
  'situation_transport',
  'dette_anterieure',
  'moyenne',
]

export type MoyenneType = 'trimestre' | 'sequence' | 'annuelle'

export interface ListeClasseModele {
  id: number
  titre_fr: string
  titre_en: string
  colonnes: ColonneListePersonnalisee[]
  moyenne_type: MoyenneType | null
}

export interface ListeClasseModelePayload {
  titre_fr: string
  titre_en: string
  colonnes: ColonneListePersonnalisee[]
  moyenne_type?: MoyenneType | null
}

export interface GenererListePayload {
  classeId: number
  titreFr: string
  titreEn: string
  colonnes: ColonneListePersonnalisee[]
  moyenneType?: MoyenneType | null
  moyenneReferenceId?: number | null
  format: 'pdf' | 'word' | 'excel'
}

export async function fetchListeClasseModeles(): Promise<ListeClasseModele[]> {
  const { data } = await http.get<ApiResponse<ListeClasseModele[]>>('/classes/liste-personnalisee/modeles')
  return data.data
}

export async function creerListeClasseModele(payload: ListeClasseModelePayload): Promise<ListeClasseModele> {
  const { data } = await http.post<ApiResponse<ListeClasseModele>>('/classes/liste-personnalisee/modeles', payload)
  return data.data
}

export async function supprimerListeClasseModele(id: number): Promise<void> {
  await http.delete(`/classes/liste-personnalisee/modeles/${id}`)
}

function paramsGeneration(payload: GenererListePayload) {
  return {
    titre_fr: payload.titreFr,
    titre_en: payload.titreEn,
    colonnes: payload.colonnes.join(','),
    moyenne_type: payload.moyenneType ?? undefined,
    moyenne_reference_id: payload.moyenneReferenceId ?? undefined,
  }
}

/** Génère et ouvre/télécharge le document dans le format demandé — mêmes helpers que le reste de l'app (cf. ClasseDetailPage). */
export async function genererListePersonnalisee(payload: GenererListePayload): Promise<void> {
  const params = paramsGeneration(payload)

  if (payload.format === 'pdf') {
    await ouvrirDocument(`/classes/${payload.classeId}/liste-personnalisee/pdf`, params)
    return
  }

  const extension = payload.format === 'word' ? 'docx' : 'xlsx'
  await telechargerFichier(
    `/classes/${payload.classeId}/liste-personnalisee/${payload.format}`,
    params,
    `liste-personnalisee.${extension}`,
  )
}
