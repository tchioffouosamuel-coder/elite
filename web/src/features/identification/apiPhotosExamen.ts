import { http } from "@/shared/lib/http";
import type { ApiResponse } from "@/shared/types/api";

export interface ClasseExamen {
  id: number;
  nom: string;
  code_examen: string;
  effectif: number;
  photos: number;
}

export interface CandidatExamen {
  eleve_id: number;
  matricule: string | null;
  nom_complet: string;
  sexe: string;
  photo_url: string | null;
  photo_prete: boolean;
}

export interface DossierExamen {
  classe: { id: number; nom: string };
  code_examen: string | null;
  centre: string;
  candidats: CandidatExamen[];
  prets: number;
  manquants: number;
}

export async function fetchClassesExamen(): Promise<ClasseExamen[]> {
  const { data } = await http.get<ApiResponse<ClasseExamen[]>>(
    "/photos-examen/classes",
  );
  return data.data;
}

export async function fetchDossierExamen(
  classeId: number,
): Promise<DossierExamen> {
  const { data } = await http.get<ApiResponse<DossierExamen>>(
    `/photos-examen/classes/${classeId}`,
  );
  return data.data;
}

/**
 * Télécharge l'archive et remonte le décompte des candidats traités.
 * Un téléchargement binaire ne peut pas porter ce bilan dans son corps :
 * l'API le place dans des en-têtes dédiés.
 */
export async function telechargerArchiveExamen(
  classeId: number,
): Promise<{ traites: number; ignores: number }> {
  const response = await http.get(
    `/photos-examen/classes/${classeId}/archive`,
    { responseType: "blob" },
  );

  const disposition = response.headers["content-disposition"] as
    | string
    | undefined;
  const nom =
    disposition?.match(/filename="?([^";]+)"?/)?.[1] ?? "photos-examen.zip";

  const url = URL.createObjectURL(response.data as Blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = nom;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);

  return {
    traites: Number(response.headers["x-photos-traitees"] ?? 0),
    ignores: Number(response.headers["x-photos-ignorees"] ?? 0),
  };
}
