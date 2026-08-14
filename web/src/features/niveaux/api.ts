import { http } from "@/shared/lib/http";
import type { ApiResponse } from "@/shared/types/api";

export interface Niveau {
  id: number;
  code: string;
  name_fr: string;
  name_en: string;
  school_id: number;
  school?: {
    id: number;
    name: string;
  };
  sous_system_id: number | null;
  sous_systeme?: {
    id: number;
    code: string;
    nom: string;
  } | null;
  created_at: string;
  updated_at: string;
}

export interface NiveauPayload {
  code: string;
  name_fr: string;
  name_en: string;
  school_id: number;
  sous_system_id?: number | null;
}

export async function fetchNiveaux(schoolId?: number): Promise<Niveau[]> {
  const { data } = await http.get<ApiResponse<Niveau[]> | { data: Niveau[] }>(
    "/niveaux",
    {
      params: schoolId ? { school_id: schoolId } : undefined,
    },
  );
  return data.data;
}

export async function fetchNiveau(id: number): Promise<Niveau> {
  const { data } = await http.get<ApiResponse<Niveau>>(`/niveaux/${id}`);
  return data.data;
}

export async function createNiveau(payload: NiveauPayload): Promise<Niveau> {
  const { data } = await http.post<ApiResponse<Niveau>>("/niveaux", payload);
  return data.data;
}

export async function updateNiveau(
  id: number,
  payload: NiveauPayload,
): Promise<Niveau> {
  const { data } = await http.put<ApiResponse<Niveau>>(
    `/niveaux/${id}`,
    payload,
  );
  return data.data;
}

export async function deleteNiveau(id: number): Promise<void> {
  await http.delete(`/niveaux/${id}`);
}

export async function batchDeleteNiveaux(ids: number[]): Promise<void> {
  await http.post(`/niveaux/batch-delete`, { ids });
}

export async function batchUpdateNiveaux(
  ids: number[],
  sous_system_id: number | null,
  school_id?: number | null,
): Promise<void> {
  await http.post(`/niveaux/batch-update`, { ids, sous_system_id, school_id });
}
