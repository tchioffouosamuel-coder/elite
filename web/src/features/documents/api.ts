import { http } from "@/shared/lib/http";
import type { ApiResponse } from "@/shared/types/api";

export type FormatDocument = "pdf" | "word" | "excel" | "zip";

export type ParametreDocument =
  | "trimestre"
  | "annee"
  | "periode"
  | "paie"
  | "date"
  | "semaine"
  | "liste_classe"
  | "liste_personnel"
  | "liste_transport";

export type ParametreListe = "liste_classe" | "liste_personnel" | "liste_transport";

export interface DocumentCatalogue {
  code: string;
  libelle: string;
  categorie: string;
  unite: string;
  unite_libelle: string;
  formats: FormatDocument[];
  parametres: ParametreDocument[];
  filtres: string[];
  types_ecole: string[] | null;
}

export interface EcoleArbre {
  id: number;
  nom: string;
  type: string;
  sous_systemes: { id: number; nom: string }[];
  niveaux: { id: number; nom: string; sous_systeme_ids: number[] }[];
  classes: { id: number; nom: string; sous_systeme_id: number | null; niveau_id: number | null }[];
}

export interface ModeleListe {
  id: number;
  titre_fr: string;
  titre_en: string;
  colonnes: string[];
  moyenne_type?: string | null;
}

export interface Catalogue {
  categories: Record<string, string>;
  formats: Record<FormatDocument, string>;
  documents: DocumentCatalogue[];
  ecoles: EcoleArbre[];
  annees: { libelle: string; active: boolean; archivee: boolean }[];
  trimestres: { ordre: number; libelle: string }[];
  colonnes: Record<ParametreListe, { cle: string; libelle: string }[]>;
  modeles_listes: Record<ParametreListe, ModeleListe[]>;
}

export interface Perimetre {
  school_id?: number;
  sous_systeme_id?: number;
  niveau_id?: number;
  classe_id?: number;
  eleve_id?: number;
  personnel_id?: number;
}

export interface ConfigListe {
  titre_fr: string;
  titre_en: string;
  colonnes: string[];
  moyenne_type?: string;
}

export interface ParametresDocuments {
  trimestre?: string;
  annee?: string;
  du?: string;
  au?: string;
  mois?: number;
  annee_paie?: number;
  date?: string;
  semaine?: string;
  liste_classe?: ConfigListe;
  liste_personnel?: ConfigListe;
  liste_transport?: ConfigListe;
}

export interface SelectionDocument {
  code: string;
  formats: FormatDocument[];
}

export interface ResumePaquet {
  total: number;
  par_document: Record<string, number>;
  apercu: string[];
  token?: string;
}

export interface EtatPaquet {
  total: number;
  traites: number;
  fichiers: number;
  erreurs: number;
  termine: boolean;
  derniere_erreur: string | null;
}

/**
 * Le centre de documents raisonne sur TOUTES les écoles du compte, quel que
 * soit l'établissement choisi dans le sélecteur : « 0 » n'est écrasé par
 * aucun intercepteur et fait passer l'API en mode agrégé (cf.
 * ScopeEtablissement) — le périmètre se choisit dans la page elle-même.
 */
const TOUTES_LES_ECOLES = { headers: { "X-School-Id": "0" } };

export async function fetchCatalogueDocuments(): Promise<Catalogue> {
  const { data } = await http.get<ApiResponse<Catalogue>>("/documents/catalogue", TOUTES_LES_ECOLES);
  return data.data;
}

export async function fetchCiblesDocuments(
  type: "eleve" | "personnel",
  id: number,
): Promise<{ id: number; nom: string; matricule: string | null }[]> {
  const { data } = await http.get<ApiResponse<{ id: number; nom: string; matricule: string | null }[]>>(
    "/documents/cibles",
    { ...TOUTES_LES_ECOLES, params: type === "eleve" ? { type, classe_id: id } : { type, school_id: id } },
  );
  return data.data;
}

export async function preparerPaquet(
  documents: SelectionDocument[],
  perimetre: Perimetre,
  parametres: ParametresDocuments,
  simulation: boolean,
): Promise<ResumePaquet> {
  const { data } = await http.post<ApiResponse<ResumePaquet>>("/documents/paquets", {
    documents,
    perimetre,
    parametres,
    simulation,
  }, TOUTES_LES_ECOLES);
  return data.data;
}

export async function traiterPaquet(token: string): Promise<EtatPaquet> {
  const { data } = await http.post<ApiResponse<EtatPaquet>>(`/documents/paquets/${token}/traiter`, null, TOUTES_LES_ECOLES);
  return data.data;
}

export async function supprimerPaquet(token: string): Promise<void> {
  await http.delete(`/documents/paquets/${token}`, TOUTES_LES_ECOLES);
}

export const ENTETES_TOUTES_LES_ECOLES = TOUTES_LES_ECOLES.headers;
