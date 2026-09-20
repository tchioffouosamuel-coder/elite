import { http } from "@/shared/lib/http";
import { useDocumentPreviewStore } from "@/shared/store/documentPreviewStore";

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

  const blobUrl = URL.createObjectURL(response.data as Blob);
  const a = document.createElement("a");
  a.href = blobUrl;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(blobUrl);
}

function blobEnDataUrl(blob: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result as string);
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(blob);
  });
}

/**
 * Affiche un PDF en aperçu plein écran dans l'application (plutôt que de
 * l'ouvrir dans un nouvel onglet) : l'utilisateur reste dans son contexte de
 * travail et valide l'impression depuis la boîte de dialogue du navigateur
 * plutôt que d'être redirigé vers une autre page.
 *
 * `data:` plutôt qu'un blob URL : dans l'app desktop (fenêtre chargée en
 * `file://`), Electron/Chromium refuse de charger un `<iframe src="blob:...">`
 * — restriction au niveau du navigateur, indépendante de la CSP — alors
 * qu'une URI `data:` s'affiche sans problème dans ce contexte comme dans un
 * navigateur classique.
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
  const dataUrl = await blobEnDataUrl(response.data as Blob);

  useDocumentPreviewStore.getState().open(dataUrl, titre);
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
  const dataUrl = await blobEnDataUrl(response.data as Blob);

  await new Promise<void>((resolve, reject) => {
    const iframe = document.createElement("iframe");
    let nettoye = false;

    const nettoyer = () => {
      if (nettoye) return;
      nettoye = true;
      iframe.remove();
    };

    iframe.style.position = "fixed";
    iframe.style.left = "-10000px";
    iframe.style.top = "0";
    iframe.style.width = "1px";
    iframe.style.height = "1px";
    iframe.style.border = "0";
    iframe.style.opacity = "0";
    iframe.setAttribute("aria-hidden", "true");

    iframe.onload = () => {
      try {
        const fenetre = iframe.contentWindow;
        if (!fenetre) throw new Error("Fenêtre d'impression indisponible.");

        fenetre.focus();
        fenetre.print();
        window.setTimeout(nettoyer, 60_000);
        resolve();
      } catch (error) {
        nettoyer();
        reject(error);
      }
    };

    iframe.onerror = () => {
      nettoyer();
      reject(new Error("Impossible de charger le reçu à imprimer."));
    };

    document.body.appendChild(iframe);
    iframe.src = dataUrl;
  });
}
