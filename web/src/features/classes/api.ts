import { http } from "@/shared/lib/http";
import type { ApiResponse } from "@/shared/types/api";

export interface Niveau {
  id: number;
  code: string;
  name_fr: string;
  name_en: string;
  school_id: number;
}

export interface AnneeScolaire {
  id: number;
  libelle: string;
  date_debut: string;
  date_fin: string;
  is_active: boolean;
}

export interface Responsable {
  id: number;
  nom_complet: string;
}

export interface School {
  id: number;
  name: string;
  code: string;
}

export interface Classe {
  id: number;
  nom: string;
  sigle: string | null;
  niveau_classe: string | null;
  filiere: string | null;
  code_examen: string | null;
  capacite: number | null;
  qr_token: string | null;
  effectif?: number;
  school_id?: number;
  niveau_id: number;
  niveau_scolaire_id: number | null;
  sous_systeme_id: number | null;
  annee_scolaire_id: number;
  niveau: { id: number; code: string; name_fr: string } | null;
  /** Niveau d'enseignement (SIL, CP…) — primaire et maternelle uniquement. */
  niveau_scolaire: { id: number; code: string; libelle: string } | null;
  sous_systeme: { id: number; code: string; nom: string } | null;
  school?: School | null;
  professeur_principal_id: number | null;
  titulaire_id: number | null;
  titulaire: Responsable | null;
  surveillant_general_id: number | null;
  censeur_id: number | null;
  conseiller_orientation_id: number | null;
  professeur_principal: Responsable | null;
  surveillant_general: Responsable | null;
  censeur: Responsable | null;
  conseiller_orientation: Responsable | null;
}

export interface ClassePayload {
  niveau_id: number;
  niveau_scolaire_id?: number | null;
  sous_systeme_id?: number | null;
  school_id?: number | null;
  annee_scolaire_id: number;
  nom: string;
  sigle?: string | null;
  niveau_classe?: string | null;
  filiere?: string | null;
  code_examen?: string | null;
  capacite?: number | null;
  professeur_principal_id?: number | null;
  titulaire_id?: number | null;
  surveillant_general_id?: number | null;
  censeur_id?: number | null;
  conseiller_orientation_id?: number | null;
}

export async function fetchNiveaux(): Promise<Niveau[]> {
  const { data } = await http.get<ApiResponse<Niveau[]>>("/niveaux");
  return data.data;
}

export async function fetchAnneesScolaires(): Promise<AnneeScolaire[]> {
  const { data } =
    await http.get<ApiResponse<AnneeScolaire[]>>("/annees-scolaires");
  return data.data;
}

export async function fetchClasses(
  anneeScolaireId?: number,
): Promise<Classe[]> {
  const { data } = await http.get<ApiResponse<Classe[]>>("/classes", {
    params: anneeScolaireId
      ? { annee_scolaire_id: anneeScolaireId }
      : undefined,
  });
  return data.data;
}

/** Classes d'une autre école du complexe (transfert d'élève), sans changer l'école active. */
export async function fetchClassesForSchool(
  schoolId: number,
): Promise<Classe[]> {
  const { data } = await http.get<ApiResponse<Classe[]>>("/classes", {
    headers: { "X-School-Id": String(schoolId) },
  });
  return data.data;
}

export async function createClasse(payload: ClassePayload): Promise<Classe> {
  const { data } = await http.post<ApiResponse<Classe>>("/classes", payload);
  return data.data;
}

export async function fetchClasse(id: number): Promise<Classe> {
  const { data } = await http.get<ApiResponse<Classe>>(`/classes/${id}`);
  return data.data;
}

/** Classe dont l'utilisateur connecté est titulaire (primaire/maternelle), ou null. */
export async function fetchMaClasse(): Promise<Classe | null> {
  const { data } = await http.get<ApiResponse<Classe | null>>("/ma-classe");
  return data.data;
}

export async function updateClasse(
  id: number,
  payload: ClassePayload,
): Promise<Classe> {
  const { data } = await http.put<ApiResponse<Classe>>(
    `/classes/${id}`,
    payload,
  );
  return data.data;
}

export async function deleteClasse(id: number): Promise<void> {
  await http.delete(`/classes/${id}`);
}

export async function fetchSousSystemes(): Promise<
  Array<{ id: number; code: string; nom: string }>
> {
  const { data } =
    await http.get<
      ApiResponse<Array<{ id: number; code: string; nom: string }>>
    >("/sous-systemes");
  return data.data;
}

export async function fetchSchools(): Promise<School[]> {
  const { data } = await http.get<ApiResponse<School[]>>("/schools");
  return data.data;
}

export async function bulkUpdateClasses(
  classIds: number[],
  updates: {
    school_id?: number | null;
    sous_systeme_id?: number | null;
    niveau_id?: number | null;
  },
): Promise<void> {
  await http.put("/classes/bulk-update", { class_ids: classIds, ...updates });
}
