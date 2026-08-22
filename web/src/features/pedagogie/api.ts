import { http } from "@/shared/lib/http";
import type { ApiResponse } from "@/shared/types/api";
import type { Departement } from "@/features/personnel/api";

/**
 * Compétence évaluée du primaire et de la maternelle : l'unité que le bulletin
 * note. Elle porte le barème et les volets ; les matières n'en sont que le
 * contenu enseigné.
 */
export interface Competence {
  id: number;
  label_fr: string;
  label_en: string | null;
  abbreviation: string | null;
  /** Barème propre : la moyenne générale ramène le total obtenu sur 20. */
  notation: number;
  evalue_pratique: boolean;
  /** Volets évalués, dans l'ordre d'affichage du bulletin. */
  volets: string[];
  /** Points par volet ; à parts égales à défaut de réglage explicite. */
  repartition_volets: Record<string, number>;
  ordre: number;
  statut: string;
  matieres_count?: number;
  classes_count?: number;
  matieres?: { id: number; nom: string; nom_en: string | null; abbreviation: string | null }[];
  school_id?: number;
  school?: { id: number; name: string; code: string; type: "maternelle" | "primaire" | "secondaire" } | null;
}

export interface CompetencePayload {
  label_fr: string;
  label_en?: string | null;
  abbreviation?: string | null;
  notation: number;
  evalue_pratique?: boolean;
  /** La somme doit égaler `notation` — l'API refuse l'écart. */
  repartition_volets?: Record<string, number> | null;
  ordre?: number | null;
  statut?: string;
  school_id?: number | null;
}

export interface Matiere {
  id: number;
  nom: string;
  nom_en: string | null;
  abbreviation: string | null;
  statut: string;
  /** Renseignée au primaire et en maternelle ; nulle au secondaire. */
  competence_id: number | null;
  competence?: { id: number; label_fr: string; label_en: string | null; notation: number } | null;
  /** Absent des réponses de création/modification, qui ne comptent pas les classes. */
  classes_count?: number;
  departement: Departement | null;
  school_id?: number;
  school?: { id: number; name: string; code: string; type: "maternelle" | "primaire" | "secondaire" } | null;
}

export interface ClasseEnseignantMatiere {
  classe_matiere_id: number;
  classe: { id: number; nom: string } | null;
  enseignant: { id: number; nom_complet: string } | null;
  coefficient: number;
}

export interface MatierePayload {
  nom: string;
  nom_en?: string | null;
  abbreviation?: string | null;
  departement_id?: number | null;
  school_id?: number | null;
  /** Compétence dont la matière est un contenu — primaire et maternelle. */
  competence_id?: number | null;
}

/** Compétence attribuée à une classe, avec l'enseignant qui la tient. */
export interface ClasseCompetence {
  classe_competence_id: number;
  competence: Competence | null;
  enseignant: { id: number; nom_complet: string } | null;
  groupe: number;
  statut: string;
}

export interface ClasseMatiere {
  id: number;
  matiere: { id: number; nom: string; abbreviation: string | null };
  enseignant: { id: number; nom_complet: string } | null;
  coefficient: number;
  quota_horaire: number | null;
  groupe: number;
  competences: string | null;
  statut: string;
}

export interface ClasseMatierePayload {
  matiere_id: number;
  personnel_id?: number | null;
  coefficient: number;
  quota_horaire?: number | null;
  groupe?: number;
}

export interface Sequence {
  id: number;
  ordre: number;
  libelle: string;
}

export interface Trimestre {
  id: number;
  annee_scolaire_id: number;
  libelle: string;
  ordre: number;
  date_debut: string | null;
  date_fin: string | null;
  is_active: boolean;
  sequences: Sequence[];
}

export async function fetchMatieres(): Promise<Matiere[]> {
  const { data } = await http.get<ApiResponse<Matiere[]>>("/matieres");
  return data.data;
}

/** Classes où cette matière est enseignée, pour la modale « Enseignée dans X classe(s) ». */
export async function fetchMatiereClasses(id: number): Promise<ClasseEnseignantMatiere[]> {
  const { data } = await http.get<ApiResponse<ClasseEnseignantMatiere[]>>(`/matieres/${id}/classes`);
  return data.data;
}

export async function createMatiere(payload: MatierePayload): Promise<Matiere> {
  const { data } = await http.post<ApiResponse<Matiere>>("/matieres", payload);
  return data.data;
}

export async function updateMatiere(
  id: number,
  payload: MatierePayload,
): Promise<Matiere> {
  const { data } = await http.put<ApiResponse<Matiere>>(
    `/matieres/${id}`,
    payload,
  );
  return data.data;
}

export async function deleteMatiere(id: number): Promise<void> {
  await http.delete(`/matieres/${id}`);
}

export async function batchDeleteMatieres(ids: number[]): Promise<void> {
  await http.post("/matieres/batch-delete", { ids });
}

export async function fetchClasseMatieres(
  classeId: number,
): Promise<ClasseMatiere[]> {
  const { data } = await http.get<ApiResponse<ClasseMatiere[]>>(
    `/classes/${classeId}/matieres`,
  );
  return data.data;
}

export async function affecterMatiere(
  classeId: number,
  payload: ClasseMatierePayload,
): Promise<ClasseMatiere> {
  const { data } = await http.post<ApiResponse<ClasseMatiere>>(
    `/classes/${classeId}/matieres`,
    payload,
  );
  return data.data;
}

export interface ClasseMatiereUpdatePayload {
  personnel_id?: number | null;
  coefficient?: number;
  quota_horaire?: number | null;
}

export async function modifierAffectation(
  classeMatiereId: number,
  payload: ClasseMatiereUpdatePayload,
): Promise<ClasseMatiere> {
  const { data } = await http.put<ApiResponse<ClasseMatiere>>(
    `/classe-matieres/${classeMatiereId}`,
    payload,
  );
  return data.data;
}

export async function retirerMatiere(classeMatiereId: number): Promise<void> {
  await http.delete(`/classe-matieres/${classeMatiereId}`);
}

export async function copierAffectations(payload: {
  affectation_ids: number[];
  classe_ids: number[];
}): Promise<{ copiees: number; ignorees: number }> {
  const { data } = await http.post<ApiResponse<{ copiees: number; ignorees: number }>>(
    "/classe-matieres/copier",
    payload,
  );
  return data.data;
}

export async function fetchTrimestres(): Promise<Trimestre[]> {
  const { data } = await http.get<ApiResponse<Trimestre[]>>("/trimestres");
  return data.data;
}

export interface MonAffectation {
  classe_matiere_id: number;
  classe_id: number;
  classe: string;
  matiere: string;
}

/**
 * Classes et matières où l'utilisateur connecté enseigne, toute l'année —
 * contrairement à `fetchMesAffectations` de « Ma journée », qui ne garde que
 * les créneaux prévus le jour même.
 */
export async function fetchMesAffectationsActives(): Promise<MonAffectation[]> {
  const { data } = await http.get<ApiResponse<MonAffectation[]>>("/classe-matieres/mes-affectations");
  return data.data;
}

/* ------------------------------------------------------------------ */
/* Compétences évaluées — primaire et maternelle                       */
/* ------------------------------------------------------------------ */

export async function fetchCompetences(): Promise<Competence[]> {
  const { data } = await http.get<ApiResponse<Competence[]>>("/competences");
  return data.data;
}

export async function creerCompetence(payload: CompetencePayload): Promise<Competence> {
  const { data } = await http.post<ApiResponse<Competence>>("/competences", payload);
  return data.data;
}

export async function modifierCompetence(id: number, payload: CompetencePayload): Promise<Competence> {
  const { data } = await http.put<ApiResponse<Competence>>(`/competences/${id}`, payload);
  return data.data;
}

export async function supprimerCompetence(id: number): Promise<void> {
  await http.delete(`/competences/${id}`);
}

/** Compétences attribuées à une classe. */
export async function fetchCompetencesClasse(classeId: number): Promise<ClasseCompetence[]> {
  const { data } = await http.get<ApiResponse<ClasseCompetence[]>>(`/classes/${classeId}/competences`);
  return data.data;
}

/**
 * Attribue des compétences à une classe. Les matières de chaque compétence y
 * sont installées d'office — c'est tout l'objet du bloc.
 */
export async function attribuerCompetences(
  classeId: number,
  competenceIds: number[],
  personnelId?: number | null,
): Promise<{ attribuees: number; matieres: number }> {
  const { data } = await http.post<ApiResponse<{ attribuees: number; matieres: number }>>(
    `/classes/${classeId}/competences`,
    { competence_ids: competenceIds, personnel_id: personnelId ?? null },
  );
  return data.data;
}

export async function modifierAttributionCompetence(
  classeCompetenceId: number,
  payload: { personnel_id?: number | null; groupe?: number; statut?: string },
): Promise<void> {
  await http.put(`/classe-competences/${classeCompetenceId}`, payload);
}

export async function retirerCompetenceClasse(classeCompetenceId: number): Promise<void> {
  await http.delete(`/classe-competences/${classeCompetenceId}`);
}
