import { http } from "@/shared/lib/http";
import { useDocumentPreviewStore } from "@/shared/store/documentPreviewStore";
import { imprimerPdf } from "@/shared/lib/pdf";

/**
 * Téléchargement de fichier authentifié (Excel/Word/PDF) : l'API exige un
 * Bearer token, impossible via un simple <a href>. On récupère le fichier en
 * blob puis on déclenche le téléchargement via un lien éphémère — contrairement
 * à un onglet PDF, un clic synthétique sur un <a download> n'est pas bloqué
 * par le pop-up blocker du navigateur.
 */
export async function telechargerFichier(
  url: string,
  params?: Record<string, string | number | undefined>,
  nomParDefaut = "export",
  headers?: Record<string, string>,
): Promise<void> {
  const response = await http.get(url, {
    params,
    headers,
    responseType: "blob",
  });

  const disposition = response.headers["content-disposition"] as
    | string
    | undefined;
  const match = disposition?.match(/filename="?([^";]+)"?/);
  const filename = match?.[1] ?? nomParDefaut;

  telechargerBlob(response.data as Blob, filename);
}

export function telechargerBlob(blob: Blob, filename: string): void {
  const blobUrl = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = blobUrl;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(blobUrl);
}

async function verifierPdf(blob: Blob): Promise<Blob> {
  const signature = await blob.slice(0, 5).text();
  if (signature !== "%PDF-") {
    throw new Error("Le serveur n'a pas renvoyé un fichier PDF valide.");
  }

  return blob;
}

/**
 * Affiche un PDF en aperçu plein écran dans l'application (plutôt que de
 * l'ouvrir dans un nouvel onglet) : l'utilisateur reste dans son contexte de
 * travail et valide l'impression depuis la boîte de dialogue du navigateur
 * plutôt que d'être redirigé vers une autre page.
 *
 * Le PDF est transmis au lecteur embarque sous forme binaire, y compris
 * hors-ligne dans le client desktop charge en file://.
 */
export async function ouvrirDocument(
  url: string,
  params?: Record<string, string | number | undefined>,
  headers?: Record<string, string>,
  titre?: string,
): Promise<void> {
  const response = await http.get(url, {
    params,
    headers,
    responseType: "blob",
  });
  const document = await verifierPdf(response.data as Blob);

  useDocumentPreviewStore.getState().open(document, titre);
}

/**
 * Charge un PDF authentifié dans une iframe invisible et déclenche
 * immédiatement l'impression. L'iframe reste en place quelques instants après
 * `print()` pour ne pas couper le dialogue d'impression du navigateur.
 */
export async function imprimerDocument(
  url: string,
  params?: Record<string, string | number | undefined>,
  headers?: Record<string, string>,
): Promise<void> {
  const response = await http.get(url, {
    params,
    headers,
    responseType: "blob",
  });
  await imprimerPdf(await verifierPdf(response.data as Blob));
}
