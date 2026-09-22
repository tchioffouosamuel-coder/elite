import { http } from "@/shared/lib/http";
import { ouvrirDocument, telechargerFichier } from "@/shared/lib/download";
import type { ApiResponse } from "@/shared/types/api";

export interface School {
  id: number;
  name: string;
  code: string;
  type: "maternelle" | "primaire" | "secondaire";
}

export interface Departement {
  id: number;
  nom: string;
  school_id?: number;
  school?: School | null;
  head_personnel_id: number | null;
  head_personnel?: {
    id: number;
    nom_complet: string;
  } | null;
  matieres?: Array<{ id: number; nom: string }>;
}

export type SituationMatrimoniale =
  | "celibataire"
  | "marie"
  | "divorce"
  | "veuf";

export type StatutParent = "vivant" | "decede" | "";
export type SexeEnfant = "M" | "F" | "";
export type TypeContrat = "CDI" | "CDD";
export type StatutContrat = "essai" | "permanent" | "vacataire";
export type MethodeValidationSeance = "qr" | "code" | "libre";

export interface PersonnelEnfant {
  nom_complet: string | null;
  sexe: SexeEnfant | null;
  date_naissance: string | null;
}

/** Dossier administratif de l'agent, hors identité et fonction. */
export interface DossierPersonnel {
  affectation: string | null;
  civilite: string | null;
  sexe: "M" | "F" | null;
  date_naissance: string | null;
  numero_cni: string | null;
  numero_cnps: string | null;
  /** Coordonnées bancaires — pour le virement du salaire, cf. bordereau de virement. */
  banque_id: number | null;
  banque: string | null;
  numero_compte: string | null;
  /**
   * Comment cet agent prouve sa présence pour valider une séance dans « Ma
   * journée » : `qr` (scanner le QR de la salle, par défaut), `code`
   * (saisir à la main le code court affiché à côté du QR), ou `libre`
   * (aucune preuve exigée).
   */
  methode_validation_seance: MethodeValidationSeance | null;
  departement_origine: string | null;
  residence: string | null;
  telephone: string | null;
  telephone_2: string | null;
  situation_matrimoniale: SituationMatrimoniale | null;
  nombre_enfants: number | null;
  diplome_professionnel: string | null;
  diplome_academique: string | null;
  email: string | null;
  date_embauche: string | null;
  date_fin: string | null;
  /** Retombe sur naissance + 60 ans quand la date n'est pas saisie. */
  date_retraite: string | null;
  /** Années pleines depuis `date_embauche` (jusqu'à `date_fin` si l'agent est sorti) — calculée côté API, lecture seule. */
  anciennete: number | null;
  pere_nom_complet: string | null;
  pere_statut: StatutParent | null;
  pere_telephone: string | null;
  mere_nom_complet: string | null;
  mere_statut: StatutParent | null;
  mere_telephone: string | null;
  enfants: PersonnelEnfant[];
  type_contrat: TypeContrat | null;
  statut_contrat: StatutContrat | null;
  /** Catégorie/échelon de la grille salariale, ex. "5C". */
  categorie_echelon: string | null;
  /** Grade MINEDUB : IPEG/IEG/IEMP/IAEG/IC/MP/MC (public) ou CAPIEMP/Licence/BAC/Probatoire/BEPC-CAP/CEPC/Maitre des Parents/Maitre Communautaire (privé). */
  grade_minedub: string | null;
  absent_depuis: string | null;
  motif_absence: string | null;
  dossier_disciplinaire: boolean;
  date_deces: string | null;
}

export interface Personnel extends DossierPersonnel {
  id: number;
  matricule: string | null;
  nom_complet: string;
  fonction_id: number;
  fonction: string;
  departement: Departement | null;
  statut: "actif" | "ex_employe";
  a_un_compte: boolean;
  school_id?: number;
  school?: School | null;
}

export type PersonnelPayload = Partial<DossierPersonnel> & {
  nom_complet: string;
  fonction_id: number;
  departement_id?: number | null;
  matricule?: string | null;
  statut?: "actif" | "ex_employe";
  school_id?: number | null;
  enfants?: PersonnelEnfant[];
};

export interface FonctionReferentiel {
  id: number;
  school_id: number;
  school?: School | null;
  label_fr: string;
  label_en: string | null;
  label: string;
  personnels_count?: number;
}

export async function fetchDepartements(): Promise<Departement[]> {
  const { data } = await http.get<ApiResponse<Departement[]>>("/departements");
  return data.data;
}

export async function createDepartement(
  nom: string,
  schoolId?: number | null,
): Promise<Departement> {
  const { data } = await http.post<ApiResponse<Departement>>("/departements", {
    nom,
    school_id: schoolId ?? undefined,
  });
  return data.data;
}

export async function deleteDepartement(id: number): Promise<void> {
  await http.delete(`/departements/${id}`);
}

export async function updateDepartement(
  id: number,
  payload: { nom?: string; head_personnel_id?: number | null },
): Promise<Departement> {
  const { data } = await http.put<ApiResponse<Departement>>(
    `/departements/${id}`,
    payload,
  );
  return data.data;
}

export async function fetchDepartementDetail(id: number): Promise<Departement> {
  const { data } = await http.get<ApiResponse<Departement>>(
    `/departements/${id}`,
  );
  return data.data;
}

export interface StatsPedagogiquesParDepartement {
  departement: { id: number; nom: string };
  trimestre: { id: number; libelle: string };
  matieres: Array<{
    id: number;
    nom: string;
    effectif_eleves: number;
    moyenne: number | null;
    taux_reussite: number | null;
  }>;
  stats_consolidees: {
    effectif_total: number;
    moyenne_generale: number | null;
    taux_reussite_moyen: number | null;
  };
}

export async function fetchStatsPedagogiquesParDepartement(
  id: number,
  trimestreId?: number,
): Promise<StatsPedagogiquesParDepartement> {
  const { data } = await http.get<ApiResponse<StatsPedagogiquesParDepartement>>(
    `/departements/${id}/statistiques/pedagogiques`,
    {
      params: { trimestre_id: trimestreId },
    },
  );
  return data.data;
}

export async function exportStatistiquesAsPdf(
  id: number,
  trimestreId?: number,
  deptNom: string = "departement",
): Promise<void> {
  const url = `/departements/${id}/statistiques/pedagogiques/export-pdf`;
  const params = new URLSearchParams();
  if (trimestreId) {
    params.append("trimestre_id", trimestreId.toString());
  }

  try {
    const response = await fetch(
      `${http.defaults.baseURL}${url}?${params.toString()}`,
      {
        method: "GET",
        headers: {
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
      },
    );

    if (!response.ok) {
      throw new Error("Failed to export PDF");
    }

    const blob = await response.blob();
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `statistiques_${deptNom}_${new Date().toISOString().split("T")[0]}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
  } catch (error) {
    console.error("Error exporting PDF:", error);
    throw error;
  }
}

export async function fetchFonctionsReferentiel(): Promise<
  FonctionReferentiel[]
> {
  const { data } = await http.get<ApiResponse<FonctionReferentiel[]>>(
    "/fonctions-referentiel",
  );
  return data.data;
}

export async function fetchFonctionReferentiel(
  id: number,
): Promise<FonctionReferentiel> {
  const { data } = await http.get<ApiResponse<FonctionReferentiel>>(
    `/fonctions-referentiel/${id}`,
  );
  return data.data;
}

export async function createFonctionReferentiel(payload: {
  label_fr: string;
  label_en?: string | null;
  school_id?: number | null;
}): Promise<FonctionReferentiel> {
  const { data } = await http.post<ApiResponse<FonctionReferentiel>>(
    "/fonctions-referentiel",
    payload,
  );
  return data.data;
}

export async function updateFonctionReferentiel(
  id: number,
  payload: {
    label_fr: string;
    label_en?: string | null;
  },
): Promise<FonctionReferentiel> {
  const { data } = await http.put<ApiResponse<FonctionReferentiel>>(
    `/fonctions-referentiel/${id}`,
    payload,
  );
  return data.data;
}

export async function deleteFonctionReferentiel(id: number): Promise<void> {
  await http.delete(`/fonctions-referentiel/${id}`);
}

export async function batchDeleteFonctionsReferentiel(
  ids: number[],
): Promise<{ deleted: number; ignorees: string[] }> {
  const { data } = await http.post<
    ApiResponse<{ deleted: number; ignorees: string[] }>
  >("/fonctions-referentiel/batch-delete", { ids });
  return data.data;
}

export interface Banque {
  id: number;
  school_id: number;
  school?: School | null;
  nom: string;
  code: string | null;
  /** Compte de l'établissement dans cette banque — celui débité pour le virement des salaires. */
  numero_compte_ecole: string | null;
  personnels_count?: number;
}

export async function fetchBanques(): Promise<Banque[]> {
  const { data } = await http.get<ApiResponse<Banque[]>>("/banques");
  return data.data;
}

export async function fetchBanque(id: number): Promise<Banque> {
  const { data } = await http.get<ApiResponse<Banque>>(`/banques/${id}`);
  return data.data;
}

export async function createBanque(payload: {
  nom: string;
  code?: string | null;
  numero_compte_ecole?: string | null;
  school_id?: number | null;
}): Promise<Banque> {
  const { data } = await http.post<ApiResponse<Banque>>("/banques", payload);
  return data.data;
}

export async function updateBanque(
  id: number,
  payload: { nom: string; code?: string | null; numero_compte_ecole?: string | null },
): Promise<Banque> {
  const { data } = await http.put<ApiResponse<Banque>>(
    `/banques/${id}`,
    payload,
  );
  return data.data;
}

export async function deleteBanque(id: number): Promise<void> {
  await http.delete(`/banques/${id}`);
}

export async function batchDeleteBanques(
  ids: number[],
): Promise<{ deleted: number; ignorees: string[] }> {
  const { data } = await http.post<
    ApiResponse<{ deleted: number; ignorees: string[] }>
  >("/banques/batch-delete", { ids });
  return data.data;
}

/**
 * Règle par défaut de preuve de présence pour « Ma journée », par école et
 * en option par sous-système (nul = toute l'école). Une fiche de personnel
 * peut la surcharger via `methode_validation_seance`.
 */
export interface RegleValidationSeance {
  id: number;
  school_id: number;
  sous_systeme_id: number | null;
  sous_systeme?: string | null;
  methode_validation: "qr" | "code" | "libre";
  delai_valeur: number;
  delai_unite: "minutes" | "jours" | "semaines";
  delai_en_minutes: number;
}

export async function fetchReglesValidationSeance(): Promise<
  RegleValidationSeance[]
> {
  const { data } = await http.get<ApiResponse<RegleValidationSeance[]>>(
    "/regles-validation-seance",
  );
  return data.data;
}

export interface RegleValidationSeancePayload {
  school_id?: number | null;
  sous_systeme_id?: number | null;
  methode_validation: "qr" | "code" | "libre";
  delai_valeur: number;
  delai_unite: "minutes" | "jours" | "semaines";
}

export async function createRegleValidationSeance(
  payload: RegleValidationSeancePayload,
): Promise<RegleValidationSeance> {
  const { data } = await http.post<ApiResponse<RegleValidationSeance>>(
    "/regles-validation-seance",
    payload,
  );
  return data.data;
}

export async function updateRegleValidationSeance(
  id: number,
  payload: RegleValidationSeancePayload,
): Promise<RegleValidationSeance> {
  const { data } = await http.put<ApiResponse<RegleValidationSeance>>(
    `/regles-validation-seance/${id}`,
    payload,
  );
  return data.data;
}

export async function deleteRegleValidationSeance(id: number): Promise<void> {
  await http.delete(`/regles-validation-seance/${id}`);
}

export async function fetchPersonnels(params?: {
  search?: string;
  departement_id?: number;
  fonction_id?: number;
  fonction_label?: string;
  banque_id?: number;
  /**
   * Ne retenir que les agents éligibles à cette responsabilité : un
   * enseignant peut être désigné surveillant général d'une classe, un économe
   * non. La règle vit côté API (App\Support\Attributions), pour que le
   * formulaire et le contrôle d'accès s'accordent.
   */
  attribution?: string;
  statut?: string;
  page?: number;
  per_page?: number;
  /**
   * Force la portée à cette école plutôt qu'à l'école active du compte : un
   * super admin en mode agrégé (sans X-School-Id) verrait sinon les agents de
   * tout le complexe, dont certains inéligibles comme responsables d'une
   * classe d'une école précise.
   */
  schoolId?: number;
}): Promise<Personnel[]> {
  const { schoolId, ...query } = params ?? {};
  const { data } = await http.get<ApiResponse<Personnel[]>>("/personnels", {
    params: query,
    headers: schoolId ? { "X-School-Id": String(schoolId) } : undefined,
  });
  return data.data;
}

export type ColonneListeEnseignant =
  | "numero"
  | "matricule"
  | "nom_prenom"
  | "fonction"
  | "departement"
  | "telephone"
  | "telephone_2"
  | "email"
  | "sexe"
  | "date_naissance"
  | "anciennete"
  | "type_contrat"
  | "statut_contrat"
  | "grade_minedub"
  | "categorie_echelon"
  | "diplome_professionnel"
  | "diplome_academique"
  | "residence"
  | "affectation"
  | "compte"
  | "statut"
  | "ecole";

export const COLONNES_LISTE_ENSEIGNANT: ColonneListeEnseignant[] = [
  "numero",
  "matricule",
  "nom_prenom",
  "fonction",
  "departement",
  "telephone",
  "telephone_2",
  "email",
  "sexe",
  "date_naissance",
  "anciennete",
  "type_contrat",
  "statut_contrat",
  "grade_minedub",
  "categorie_echelon",
  "diplome_professionnel",
  "diplome_academique",
  "residence",
  "affectation",
  "compte",
  "statut",
  "ecole",
];

export const LIBELLES_COLONNES_ENSEIGNANT: Record<ColonneListeEnseignant, string> = {
  numero: "N°",
  matricule: "Matricule",
  nom_prenom: "Nom et prénom",
  fonction: "Fonction",
  departement: "Département",
  telephone: "Téléphone",
  telephone_2: "Téléphone 2",
  email: "E-mail",
  sexe: "Sexe",
  date_naissance: "Date de naissance",
  anciennete: "Ancienneté",
  type_contrat: "Type contrat",
  statut_contrat: "Statut contrat",
  grade_minedub: "Grade",
  categorie_echelon: "Catégorie/Échelon",
  diplome_professionnel: "Diplôme professionnel",
  diplome_academique: "Diplôme académique",
  residence: "Résidence",
  affectation: "Affectation",
  compte: "Compte",
  statut: "Statut",
  ecole: "École",
};

export interface ListeEnseignantModele {
  id: number;
  titre_fr: string;
  titre_en: string;
  colonnes: ColonneListeEnseignant[];
}

export interface ListeEnseignantModelePayload {
  titre_fr: string;
  titre_en: string;
  colonnes: ColonneListeEnseignant[];
}

export interface GenererListeEnseignantPayload {
  titreFr: string;
  titreEn: string;
  colonnes: ColonneListeEnseignant[];
  format: "pdf" | "word" | "excel";
  search?: string;
  departementId?: number | null;
  statut?: "actif" | "ex_employe" | "";
}

export async function fetchListeEnseignantModeles(): Promise<ListeEnseignantModele[]> {
  const { data } = await http.get<ApiResponse<ListeEnseignantModele[]>>(
    "/personnels/liste-personnalisee/modeles",
  );
  return data.data;
}

export async function creerListeEnseignantModele(
  payload: ListeEnseignantModelePayload,
): Promise<ListeEnseignantModele> {
  const { data } = await http.post<ApiResponse<ListeEnseignantModele>>(
    "/personnels/liste-personnalisee/modeles",
    payload,
  );
  return data.data;
}

export async function supprimerListeEnseignantModele(id: number): Promise<void> {
  await http.delete(`/personnels/liste-personnalisee/modeles/${id}`);
}

function paramsGenerationListeEnseignant(payload: GenererListeEnseignantPayload) {
  return {
    titre_fr: payload.titreFr,
    titre_en: payload.titreEn,
    colonnes: payload.colonnes.join(","),
    search: payload.search || undefined,
    departement_id: payload.departementId || undefined,
    statut: payload.statut || undefined,
  };
}

export async function genererListePersonnaliseeEnseignants(
  payload: GenererListeEnseignantPayload,
): Promise<void> {
  const params = paramsGenerationListeEnseignant(payload);

  if (payload.format === "pdf") {
    await ouvrirDocument(
      "/personnels/liste-personnalisee/pdf",
      params as Record<string, string | number | undefined>,
      undefined,
      "Liste personnalisée — enseignants",
    );
    return;
  }

  const extension = payload.format === "word" ? "docx" : "xlsx";
  await telechargerFichier(
    `/personnels/liste-personnalisee/${payload.format}`,
    params,
    `liste-personnalisee-enseignants.${extension}`,
  );
}

export async function fetchPersonnel(id: number): Promise<Personnel> {
  const { data } = await http.get<ApiResponse<Personnel>>(`/personnels/${id}`);
  return data.data;
}

export async function createPersonnel(
  payload: PersonnelPayload,
): Promise<Personnel> {
  const { data } = await http.post<ApiResponse<Personnel>>(
    "/personnels",
    payload,
  );
  return data.data;
}

export async function updatePersonnel(
  id: number,
  payload: PersonnelPayload,
): Promise<Personnel> {
  const { data } = await http.put<ApiResponse<Personnel>>(
    `/personnels/${id}`,
    payload,
  );
  return data.data;
}

export async function archivePersonnel(id: number): Promise<void> {
  await http.post(`/personnels/${id}/archive`);
}

export async function reactivatePersonnel(id: number): Promise<void> {
  await http.post(`/personnels/${id}/reactivate`);
}

export async function deletePersonnel(id: number): Promise<void> {
  await http.delete(`/personnels/${id}`);
}

export async function batchDeletePersonnel(
  ids: number[],
): Promise<{ deleted: number }> {
  const { data } = await http.post<ApiResponse<{ deleted: number }>>(
    "/personnels/batch-delete",
    { ids },
  );
  return data.data;
}

export async function batchArchivePersonnel(ids: number[]): Promise<void> {
  await Promise.all(ids.map((id) => archivePersonnel(id)));
}

/** Sans `email`, l'API dérive l'adresse du nom sur le domaine de l'établissement. */
export async function createLoginAccount(
  id: number,
  email?: string,
): Promise<void> {
  await http.post(`/personnels/${id}/compte`, email ? { email } : {});
}

/**
 * Rattrape les comptes ouverts avant que la connexion par téléphone
 * n'existe : leur `phone` est resté vide alors que la fiche porte un
 * numéro. Un seul appel — pas de découpage en lots côté client, l'opération
 * ne fait que mettre à jour une colonne sur des comptes déjà créés.
 */
export async function rattraperTelephonesPersonnel(): Promise<{
  maj: number;
  ignores: Array<{ personnel: string; motif: string }>;
}> {
  const { data } = await http.post<
    ApiResponse<{ maj: number; ignores: Array<{ personnel: string; motif: string }> }>
  >("/personnels/rattraper-telephones");
  return data.data;
}

export interface PaireFusionParent {
  personnel: string;
  personnel_id: number;
  tuteur: string;
  tuteur_id: number;
}

/**
 * Doublons personnel/parent détectés (même téléphone, comptes différents) —
 * aperçu affiché avant confirmation, cf. {@see fusionnerComptesParent()}.
 */
export async function apercuFusionComptesParent(): Promise<{
  paires: PaireFusionParent[];
  total: number;
}> {
  const { data } = await http.get<
    ApiResponse<{ paires: PaireFusionParent[]; total: number }>
  >("/personnels/fusion-parent/apercu");
  return data.data;
}

/**
 * Fusionne les doublons personnel/parent détectés : rattache chaque fiche
 * tuteur au compte personnel correspondant et supprime le compte parent
 * devenu superflu.
 */
export async function fusionnerComptesParent(): Promise<{
  fusionnes: number;
  paires: Array<{ personnel: string; tuteur: string }>;
}> {
  const { data } = await http.post<
    ApiResponse<{
      fusionnes: number;
      paires: Array<{ personnel: string; tuteur: string }>;
    }>
  >("/personnels/fusion-parent");
  return data.data;
}

/**
 * Change la fonction de plusieurs agents d'un coup. La fonction porte les
 * privilèges : après une reprise de fichier, les doter un par un demande
 * autant d'allers-retours qu'il y a d'enseignants.
 */
export async function batchFonctionPersonnel(
  ids: number[],
  fonctionId: number,
): Promise<{ modifies: number; ignores: number }> {
  const { data } = await http.post<
    ApiResponse<{ modifies: number; ignores: number }>
  >("/personnels/batch-fonction", { ids, fonction_id: fonctionId });
  return data.data;
}

// ------------------------------------------------------------- Suivi d'activité

export type GranulariteSuivi = "jour" | "semaine" | "mois" | "annee";

export interface SuiviActiviteResume {
  heures_prevues: number;
  heures_realisees: number;
  taux: number;
  seances_prevues: number;
  seances_realisees: number;
  seances_annulees: number;
  seances_en_retard: number;
}

export interface SuiviActivitePeriode extends SuiviActiviteResume {
  periode: string;
}

export interface SuiviActivitePersonnel {
  personnel_id: number;
  nom_complet: string;
  fonction: string | null;
  periodes: SuiviActivitePeriode[];
  totaux: SuiviActiviteResume;
}

export async function fetchSuiviActivite(params: {
  date_debut: string;
  date_fin: string;
  granularite: GranulariteSuivi;
  personnel_id?: number | null;
  sous_systeme_id?: number | null;
  departement_id?: number | null;
}): Promise<SuiviActivitePersonnel[]> {
  const { data } = await http.get<ApiResponse<SuiviActivitePersonnel[]>>(
    "/personnels/suivi-activite",
    { params },
  );
  return data.data;
}
