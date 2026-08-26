import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import { ouvrirDocument } from '@/shared/lib/download'

/** Indicateurs d'une catégorie d'élèves (redoublants, garçons, total…). */
export interface CategoriePedagogique {
  effectif: number
  notes: number
  admis: number
  honneur: number
  cvwa: number
  cwa: number
  ca: number
  caa: number
  cna: number
  taux_reussite: number
  taux_participation: number
}

export type CleCategorie = 'redoublants' | 'nouveaux' | 'garcons' | 'filles' | 'total'

export interface StatsPedagogiquesClasse {
  classe: { id: number; nom: string }
  seuil_honneur: number
  categories: Record<CleCategorie, CategoriePedagogique>
  moyenne_classe: number | null
  moyenne_plus_forte: number | null
  moyenne_plus_faible: number | null
}

export interface StatsPedagogiques {
  trimestre: { id: number; libelle: string }
  classes: StatsPedagogiquesClasse[]
  consolide: Record<CleCategorie, CategoriePedagogique>
}

/** Heures au secondaire, journées au primaire et en maternelle : les clés
 *  gardent leur nom d'origine, `unite` dit ce qu'elles comptent. */
export type UniteAbsence = 'heures' | 'jours'

export interface BilanDisciplinaire {
  effectif: number
  unite: UniteAbsence
  total_hj: number
  total_hnj: number
  moyenne_hnj: number
  total_hnj_garcons: number
  moyenne_hnj_garcons: number
  total_hnj_filles: number
  moyenne_hnj_filles: number
  eleve_plus_absent: { nom_complet: string; heures_non_justifiees: number } | null
}

export interface StatsDisciplinairesClasse {
  classe: { id: number; nom: string }
  bilan: BilanDisciplinaire
  sanctions_par_type: Record<string, number>
  eleves_sanctionnes: number
  total_sanctions: number
}

export interface StatsDisciplinaires {
  trimestre: { id: number; libelle: string }
  classes: StatsDisciplinairesClasse[]
  consolide: {
    total_sanctions: number
    eleves_sanctionnes: number
    effectif: number
    unite: UniteAbsence
    heures_justifiees: number
    heures_non_justifiees: number
    sanctions_par_type: Record<string, number>
  }
}

/**
 * `schoolId` cible une école précise du complexe sans changer l'école active.
 * En mode agrégé (super admin, "Toutes les écoles"), ces statistiques ne
 * peuvent pas se calculer pour tout le complexe à la fois — matières et
 * compétences, séquences, ne coïncident pas d'une école à l'autre — donc
 * l'API exige une école explicite plutôt que d'en deviner une silencieusement.
 */
function enTeteEcole(schoolId?: number | null): Record<string, string> | undefined {
  return schoolId ? { 'X-School-Id': String(schoolId) } : undefined
}

export async function fetchStatsPedagogiques(trimestreId?: number, schoolId?: number | null): Promise<StatsPedagogiques> {
  const { data } = await http.get<ApiResponse<StatsPedagogiques>>('/statistiques/pedagogiques', {
    params: { trimestre_id: trimestreId },
    headers: enTeteEcole(schoolId),
  })
  return data.data
}

export async function fetchStatsDisciplinaires(trimestreId?: number, schoolId?: number | null): Promise<StatsDisciplinaires> {
  const { data } = await http.get<ApiResponse<StatsDisciplinaires>>('/statistiques/disciplinaires', {
    params: { trimestre_id: trimestreId },
    headers: enTeteEcole(schoolId),
  })
  return data.data
}

export function ouvrirStatsPedagogiquesPdf(trimestreId?: number, schoolId?: number | null): Promise<void> {
  return ouvrirDocument('/statistiques/pedagogiques/pdf', { trimestre_id: trimestreId }, enTeteEcole(schoolId))
}

export function ouvrirStatsDisciplinairesPdf(trimestreId?: number, schoolId?: number | null): Promise<void> {
  return ouvrirDocument('/statistiques/disciplinaires/pdf', { trimestre_id: trimestreId }, enTeteEcole(schoolId))
}

export const LIBELLES_CATEGORIE: Record<CleCategorie, string> = {
  redoublants: 'Redoublants',
  nouveaux: 'Nouveaux',
  garcons: 'Garçons',
  filles: 'Filles',
  total: 'Total',
}

/** Bornes des cotes, reprises de _smapp. */
export const BANDES: { cle: keyof CategoriePedagogique; libelle: string }[] = [
  { cle: 'cvwa', libelle: '≥ 16' },
  { cle: 'cwa', libelle: '14 – 16' },
  { cle: 'ca', libelle: '12 – 14' },
  { cle: 'caa', libelle: '10 – 12' },
  { cle: 'cna', libelle: '< 10' },
]

export const LIBELLES_SANCTION: Record<string, string> = {
  corvee: 'Corvée',
  exclusion_temporaire: 'Exclusion temporaire',
  exclusion_definitive: 'Exclusion définitive',
  autre: 'Autre',
}
