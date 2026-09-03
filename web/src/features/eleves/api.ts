import { http } from "@/shared/lib/http";
import type { ApiResponse, Pagination } from "@/shared/types/api";

export interface TuteurTelephone {
  id?: number;
  numero: string;
  is_principal: boolean;
}

export interface Tuteur {
  id: number;
  nom_complet: string;
  telephone: string | null;
  telephones: TuteurTelephone[];
  email: string | null;
  profession: string | null;
  lien_parente: string | null;
  is_principal: boolean;
}

/** Résultat de la recherche par similarité de nom, pour l'autocomplétion à l'inscription. */
export interface TuteurSuggestion {
  id: number;
  nom_complet: string;
  telephone: string | null;
  telephones: TuteurTelephone[];
  email: string | null;
  profession: string | null;
  adresse: string | null;
}

export interface Eleve {
  id: number;
  matricule: string | null;
  nom_complet: string;
  sexe: "M" | "F";
  date_naissance: string | null;
  age: number | null;
  lieu_naissance: string | null;
  numero_acte_naissance: string | null;
  adresse: string | null;
  nationalite: string | null;
  refugie: "Oui" | "Non" | null;
  deplace_interne: "Oui" | "Non" | null;
  bororo: "Oui" | "Non" | null;
  baka: "Oui" | "Non" | null;
  photo_url: string | null;
  groupe_sanguin: string | null;
  situation_sanitaire: string | null;
  aptitude: "apte" | "inapte" | null;
  allergies: string | null;
  redoublant: boolean;
  statut: "actif" | "parti" | "exclu";
  school_id: number | null;
  school: {
    id: number;
    name: string;
    code: string;
    type: "maternelle" | "primaire" | "secondaire";
  } | null;
  classe: { id: number; nom: string; niveau: string | null } | null;
  tuteurs: Tuteur[];
}

export interface EleveTuteurInput {
  /** Renseigné uniquement quand une suggestion de l'autocomplétion a été choisie — sinon, saisie considérée comme un nouveau tuteur. */
  tuteur_id?: number;
  nom_complet: string;
  telephones: { numero: string; is_principal: boolean }[];
  profession?: string;
  lien_parente?: string;
  is_principal?: boolean;
}

export interface ElevePayload {
  classe_id?: number | null;
  nom_complet: string;
  sexe: "M" | "F";
  date_naissance?: string | null;
  lieu_naissance?: string | null;
  numero_acte_naissance?: string | null;
  adresse?: string | null;
  refugie?: "Oui" | "Non" | null;
  deplace_interne?: "Oui" | "Non" | null;
  bororo?: "Oui" | "Non" | null;
  baka?: "Oui" | "Non" | null;
  statut?: Eleve["statut"];
  tuteurs?: EleveTuteurInput[];
}

export async function fetchEleves(params: {
  search?: string;
  classe_id?: number;
  page?: number;
  per_page?: number;
}): Promise<{
  items: Eleve[];
  pagination: Pagination;
}> {
  const { data } = await http.get<ApiResponse<Eleve[]>>("/eleves", { params });
  return { items: data.data, pagination: data.meta!.pagination! };
}

/**
 * Recherche transverse : nom de l'élève, matricule, ou nom/téléphone d'un de
 * ses tuteurs. Résultats non paginés, bornés côté API — pensée pour une
 * recherche rapide (ex. un appel entrant dont on n'a que le numéro), pas
 * pour remplacer la liste filtrée de `fetchEleves`.
 */
export async function rechercheGlobaleEleves(q: string): Promise<Eleve[]> {
  const { data } = await http.get<ApiResponse<Eleve[]>>("/eleves/recherche-globale", { params: { q } });
  return data.data;
}

/**
 * Autocomplétion utilisée à l'inscription : propose les tuteurs déjà connus
 * de l'école dont le nom ressemble à la saisie en cours, pour éviter de
 * ressaisir un parent qui a déjà un autre enfant inscrit.
 */
export async function rechercheTuteurs(q: string): Promise<TuteurSuggestion[]> {
  const { data } = await http.get<ApiResponse<TuteurSuggestion[]>>("/tuteurs/recherche", { params: { q } });
  return data.data;
}

export async function fetchEleve(id: number): Promise<Eleve> {
  const { data } = await http.get<ApiResponse<Eleve>>(`/eleves/${id}`);
  return data.data;
}

export async function createEleve(payload: ElevePayload): Promise<Eleve> {
  const { data } = await http.post<ApiResponse<Eleve>>("/eleves", payload);
  return data.data;
}

export async function updateEleve(
  id: number,
  payload: ElevePayload,
): Promise<Eleve> {
  const { data } = await http.put<ApiResponse<Eleve>>(`/eleves/${id}`, payload);
  return data.data;
}

export async function archiveEleve(id: number): Promise<void> {
  await http.put(`/eleves/${id}`, { statut: "parti" });
}

export async function reactivateEleve(id: number): Promise<void> {
  await http.put(`/eleves/${id}`, { statut: "actif" });
}

export async function uploadElevePhoto(id: number, file: File): Promise<Eleve> {
  const formData = new FormData();
  formData.append("photo", file);
  const { data } = await http.post<ApiResponse<Eleve>>(
    `/eleves/${id}/photo`,
    formData,
    {
      headers: { "Content-Type": "multipart/form-data" },
    },
  );
  return data.data;
}

export async function deleteEleve(id: number): Promise<void> {
  await http.delete(`/eleves/${id}`);
}

export async function batchDeleteEleves(
  ids: number[],
): Promise<{ deleted: number }> {
  const { data } = await http.post<ApiResponse<{ deleted: number }>>(
    "/eleves/batch-delete",
    { ids },
  );
  return data.data;
}

/** Change la classe d'un élève au sein de la même école. */
export async function changerClasseEleve(
  id: number,
  classeId: number,
): Promise<Eleve> {
  return updateEleve(id, { classe_id: classeId } as ElevePayload);
}

/** Transfère un élève vers une classe d'une autre école du complexe (super admin uniquement). */
export async function transfererEleveEcole(
  id: number,
  schoolId: number,
  classeId: number,
): Promise<Eleve> {
  const { data } = await http.post<ApiResponse<Eleve>>(
    `/eleves/${id}/transfert`,
    {
      school_id: schoolId,
      classe_id: classeId,
    },
  );
  return data.data;
}

export async function batchChangerClasseEleves(
  ids: number[],
  classeId: number,
): Promise<{ transferes: number }> {
  const { data } = await http.post<ApiResponse<{ transferes: number }>>(
    "/eleves/batch-transfert-classe",
    { ids, classe_id: classeId },
  );
  return data.data;
}

// ---------------------------------------------------------------- Rapports

/** Une colonne = un critère compté indépendamment (pas une répartition croisée). */
export interface LigneEffectifs {
  nouveaux: number;
  redoublants: number;
  camerounais: number;
  refugies: number;
  effectif: number;
}

export interface RecapitulatifEcole {
  school: { id: number; name: string };
  classe?: { id: number; nom: string } | null;
  garcons: LigneEffectifs;
  filles: LigneEffectifs;
  total: LigneEffectifs;
}

export interface RecapitulatifSousSysteme {
  school: { id: number; name: string };
  sous_systemes: {
    sous_systeme: { id: number; nom: string } | null;
    garcons: LigneEffectifs;
    filles: LigneEffectifs;
    total: LigneEffectifs;
  }[];
}

/** `age` : notation exacte années.mois (ex. "1.2" = 1 an 2 mois), pas l'âge arrondi à l'année révolue. */
export interface LigneAge {
  age: string;
  annees: number;
  mois: number;
  garcons: number;
  filles: number;
  total: number;
  /** Liste nominative des élèves de cet âge, dépliée à la demande. */
  eleves: {
    id: number;
    matricule: string | null;
    nom_complet: string;
    sexe: "M" | "F";
    classe: string | null;
    date_naissance: string | null;
  }[];
}

export async function fetchRecapitulatifEffectifs(
  classeId?: number | null,
): Promise<RecapitulatifEcole[]> {
  const { data } = await http.get<ApiResponse<RecapitulatifEcole[]>>(
    "/eleves/recapitulatif-effectifs",
    {
      params: classeId ? { classe_id: classeId } : undefined,
    },
  );
  return data.data;
}

export async function fetchRecapitulatifSousSystemes(): Promise<
  RecapitulatifSousSysteme[]
> {
  const { data } = await http.get<ApiResponse<RecapitulatifSousSysteme[]>>(
    "/eleves/recapitulatif-sous-systemes",
  );
  return data.data;
}

export async function fetchTableauAges(params: {
  school_id?: number | null;
  sous_systeme_id?: number | null;
  classe_id?: number | null;
}): Promise<LigneAge[]> {
  const { data } = await http.get<ApiResponse<LigneAge[]>>(
    "/eleves/tableau-ages",
    { params },
  );
  return data.data;
}

/** Ouvre (ou renvoie) l'accès au portail parent pour ce tuteur — identifiant : son numéro de téléphone. */
export async function creerCompteParent(tuteurId: number): Promise<{
  user_id: number;
  identifiant: string;
  mot_de_passe_provisoire: string | null;
}> {
  const { data } = await http.post<
    ApiResponse<{
      user_id: number;
      identifiant: string;
      mot_de_passe_provisoire: string | null;
    }>
  >(`/tuteurs/${tuteurId}/compte-parent`);
  return data.data;
}

/** Ouvre (ou renvoie) l'accès au portail élève pour cette fiche — identifiant : son matricule. */
export async function creerCompteEleve(eleveId: number): Promise<{
  user_id: number;
  identifiant: string;
  mot_de_passe_provisoire: string | null;
}> {
  const { data } = await http.post<
    ApiResponse<{
      user_id: number;
      identifiant: string;
      mot_de_passe_provisoire: string | null;
    }>
  >(`/eleves/${eleveId}/compte-eleve`);
  return data.data;
}

export async function basculerAccesEleve(eleveId: number): Promise<void> {
  await http.post(`/eleves/${eleveId}/basculer-acces`);
}

export async function supprimerCompteEleve(eleveId: number): Promise<void> {
  await http.delete(`/eleves/${eleveId}/compte-eleve`);
}

// ----------------------------------------------------------- Comptes parents

export interface TuteurCompte {
  id: number;
  nom_complet: string;
  telephone: string | null;
  email: string | null;
  a_compte: boolean;
  acces_bloque: boolean;
  enfants: { id: number; nom_complet: string }[];
}

export async function fetchTuteurs(params: {
  search?: string;
  sans_compte?: boolean;
  page?: number;
  per_page?: number;
}): Promise<{ items: TuteurCompte[]; pagination: Pagination }> {
  const { data } = await http.get<ApiResponse<TuteurCompte[]>>("/tuteurs", {
    params,
  });
  return { items: data.data, pagination: data.meta!.pagination! };
}

/** Ouvre l'accès de tous les tuteurs de l'école qui n'en ont pas encore, en un seul appel — réservé aux petits effectifs, cf. `fetchTuteursSansCompte`/`assurerComptesParentChunk`. */
export async function creerComptesParentLot(): Promise<{
  crees: number;
  ignores: { tuteur: string; motif: string }[];
}> {
  const { data } = await http.post<
    ApiResponse<{ crees: number; ignores: { tuteur: string; motif: string }[] }>
  >("/tuteurs/comptes-parent-lot");
  return data.data;
}

/**
 * Liste stable des tuteurs sans accès, à découper en petits lots avant
 * d'appeler `assurerComptesParentChunk` — un seul appel dépasserait le délai
 * d'exécution du serveur pour un gros effectif (`Hash::make` est
 * délibérément coûteux).
 */
export async function fetchTuteursSansCompte(): Promise<number[]> {
  const { data } = await http.post<ApiResponse<{ ids: number[] }>>(
    "/tuteurs/comptes-parent-lot/preparer",
  );
  return data.data.ids;
}

export async function assurerComptesParentChunk(
  ids: number[],
): Promise<{ crees: number; ignores: { tuteur: string; motif: string }[] }> {
  const { data } = await http.post<
    ApiResponse<{ crees: number; ignores: { tuteur: string; motif: string }[] }>
  >("/tuteurs/comptes-parent-lot/traiter", { ids });
  return data.data;
}

export async function basculerAccesParent(tuteurId: number): Promise<void> {
  await http.post(`/tuteurs/${tuteurId}/basculer-acces`);
}

export async function supprimerCompteParent(tuteurId: number): Promise<void> {
  await http.post(`/tuteurs/${tuteurId}/supprimer-compte-parent`);
}

/** Supprime la fiche du tuteur elle-même — pas seulement son accès au portail, cf. `supprimerCompteParent`. */
export async function supprimerTuteur(tuteurId: number): Promise<void> {
  await http.delete(`/tuteurs/${tuteurId}`);
}

// ------------------------------------------------- Usage du portail parent

export interface PointSerie {
  date: string;
  total: number;
}

export interface VolumeDemandes {
  total: number;
  serie: PointSerie[];
}

export interface VolumeDemandesAvecStatut extends VolumeDemandes {
  repartition: { en_attente: number; validee: number; rejetee: number };
}

export interface ParentUsageStats {
  periode: { jours: number; debut: string; fin: string };
  adoption: {
    tuteurs_total: number;
    comptes_parent_total: number;
    taux_adoption: number;
    comptes_ouverts_serie: PointSerie[];
  };
  activite: {
    connexions_totales: number;
    parents_actifs_distincts: number;
    parents_actifs_7j: number;
    serie_quotidienne: PointSerie[];
  };
  volumes: {
    preinscriptions: VolumeDemandesAvecStatut;
    modifications: VolumeDemandesAvecStatut;
    justifications: VolumeDemandes;
    observations: VolumeDemandes;
  };
  efficience: {
    delai_moyen_preinscriptions_heures: number | null;
    delai_moyen_modifications_heures: number | null;
  };
}

export async function fetchParentUsageStats(
  jours: 7 | 30 | 90,
): Promise<ParentUsageStats> {
  const { data } = await http.get<ApiResponse<ParentUsageStats>>(
    "/parent-usage-stats",
    { params: { jours } },
  );
  return data.data;
}

export async function batchTransfererEleveEcole(
  ids: number[],
  schoolId: number,
  classeId: number,
): Promise<{ transferes: number }> {
  const { data } = await http.post<ApiResponse<{ transferes: number }>>(
    "/eleves/batch-transfert-ecole",
    { ids, school_id: schoolId, classe_id: classeId },
  );
  return data.data;
}
