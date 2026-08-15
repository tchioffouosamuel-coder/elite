import { http } from "@/shared/lib/http";
import type { ApiResponse, Pagination } from "@/shared/types/api";

export interface Tuteur {
  id: number;
  nom_complet: string;
  telephone: string | null;
  email: string | null;
  profession: string | null;
  lien_parente: string | null;
  is_principal: boolean;
}

export interface Eleve {
  id: number;
  matricule: string | null;
  nom_complet: string;
  sexe: "M" | "F";
  date_naissance: string | null;
  lieu_naissance: string | null;
  numero_acte_naissance: string | null;
  adresse: string | null;
  nationalite: string | null;
  refugie: "Oui" | "Non" | null;
  deplace_interne: "Oui" | "Non" | null;
  photo_url: string | null;
  redoublant: boolean;
  statut: "actif" | "parti" | "exclu";
  classe: { id: number; nom: string; niveau: string | null } | null;
  tuteurs: Tuteur[];
}

export interface EleveTuteurInput {
  nom_complet: string;
  telephone?: string;
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
