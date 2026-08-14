import { http } from "@/shared/lib/http";
import { estSecondaire } from "@/shared/lib/ecole";
import type { ApiResponse } from "@/shared/types/api";

export interface Remplissage {
  trimestre: { id: number; libelle: string };
  matieres: {
    classe_matiere_id: number;
    matiere: string;
    bareme?: number;
    volets?: string[];
    enseignant: string | null;
    taux: number;
  }[];
}

export interface Classement {
  trimestre: { id: number; libelle: string };
  eleves: {
    eleve_id: number;
    nom_complet: string;
    moyenne: number | null;
    rang: number | null;
    cote: string;
    mention: string | null;
  }[];
}

export interface Palmares {
  trimestre: { id: number; libelle: string };
  eleves: {
    eleve_id: number;
    nom_complet: string;
    classe: string | null;
    moyenne: number;
    heures_non_justifiees: number;
  }[];
}

/**
 * Le primaire et la maternelle ont leur propre moteur de notation : les écrans
 * de résultats sont communs, seul l'endpoint interrogé change selon l'école
 * active. Les deux variantes renvoient la même enveloppe.
 */
function endpoint(classeId: number, ressource: string): string {
  return estSecondaire()
    ? `/classes/${classeId}/${ressource}`
    : `/classes/${classeId}/${ressource}-primaire`;
}

export async function fetchRemplissage(
  classeId: number,
  trimestreId?: number,
): Promise<Remplissage> {
  const { data } = await http.get<ApiResponse<Remplissage>>(
    endpoint(classeId, "remplissage"),
    {
      params: trimestreId ? { trimestre_id: trimestreId } : undefined,
    },
  );
  return data.data;
}

export async function fetchClassement(
  classeId: number,
  trimestreId?: number,
): Promise<Classement> {
  const { data } = await http.get<ApiResponse<Classement>>(
    endpoint(classeId, "classement"),
    {
      params: trimestreId ? { trimestre_id: trimestreId } : undefined,
    },
  );
  return data.data;
}

export async function fetchPalmares(
  trimestreId?: number,
  classeId?: number,
): Promise<Palmares> {
  const { data } = await http.get<ApiResponse<Palmares>>("/palmares", {
    params: { trimestre_id: trimestreId, classe_id: classeId },
  });
  return data.data;
}

/**
 * L'API exige un Bearer token dans l'en-tête, impossible via un simple lien
 * <a href>. On ouvre un onglet vide tout de suite (dans le geste utilisateur,
 * pour éviter le blocage de pop-up), puis on y charge le PDF récupéré en
 * blob une fois la requête authentifiée terminée.
 */
export async function ouvrirBulletin(
  eleveId: number,
  trimestreId?: number,
): Promise<void> {
  const url = estSecondaire()
    ? `/eleves/${eleveId}/bulletin`
    : `/eleves/${eleveId}/bulletin-primaire`;

  return ouvrirPdf(url, trimestreId);
}

/** Tous les bulletins de la classe dans un document unique, un élève par page. */
export async function ouvrirBulletinsClasse(
  classeId: number,
  trimestreId?: number,
): Promise<void> {
  return ouvrirPdf(endpoint(classeId, "bulletins"), trimestreId);
}

async function ouvrirPdf(url: string, trimestreId?: number): Promise<void> {
  const fenetre = window.open("", "_blank");

  const response = await http.get(url, {
    params: trimestreId ? { trimestre_id: trimestreId } : undefined,
    responseType: "blob",
  });

  const blobUrl = URL.createObjectURL(response.data as Blob);

  if (fenetre) {
    fenetre.location.href = blobUrl;
  } else {
    window.open(blobUrl, "_blank");
  }
}
