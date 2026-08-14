import { http } from "@/shared/lib/http";
import type { ApiResponse } from "@/shared/types/api";

export interface SousSysteme {
  id: number;
  code: string;
  nom: string;
  description: string | null;
  nb_classes?: number;
}

export async function fetchSousSystemes(): Promise<SousSysteme[]> {
  const { data } = await http.get<ApiResponse<SousSysteme[]>>("/sous-systemes");
  return data.data;
}

export async function createSousSysteme(payload: {
  code: string;
  nom: string;
  description?: string;
}): Promise<SousSysteme> {
  const { data } = await http.post<ApiResponse<SousSysteme>>(
    "/sous-systemes",
    payload,
  );
  return data.data;
}

export async function updateSousSysteme(
  id: number,
  payload: {
    code?: string;
    nom?: string;
    description?: string;
  },
): Promise<SousSysteme> {
  const { data } = await http.put<ApiResponse<SousSysteme>>(
    `/sous-systemes/${id}`,
    payload,
  );
  return data.data;
}

export async function deleteSousSysteme(id: number): Promise<void> {
  await http.delete(`/sous-systemes/${id}`);
}
