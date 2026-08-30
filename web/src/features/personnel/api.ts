import { http } from "@/shared/lib/http";
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

export async function fetchPersonnels(params?: {
  search?: string;
  departement_id?: number;
  fonction_id?: number;
  fonction_label?: string;
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
