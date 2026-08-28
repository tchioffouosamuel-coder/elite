import { http } from '@/shared/lib/http'
import { useDocumentPreviewStore } from '@/shared/store/documentPreviewStore'

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
  nomParDefaut = 'export',
): Promise<void> {
  const response = await http.get(url, { params, responseType: 'blob' })

  const disposition = response.headers['content-disposition'] as string | undefined
  const match = disposition?.match(/filename="?([^";]+)"?/)
  const filename = match?.[1] ?? nomParDefaut

  const blobUrl = URL.createObjectURL(response.data as Blob)
  const a = document.createElement('a')
  a.href = blobUrl
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(blobUrl)
}

/**
 * Affiche un PDF en aperçu plein écran dans l'application (plutôt que de
 * l'ouvrir dans un nouvel onglet) : l'utilisateur reste dans son contexte de
 * travail et valide l'impression depuis la boîte de dialogue du navigateur
 * plutôt que d'être redirigé vers une autre page.
 */
export async function ouvrirDocument(
  url: string,
  params?: Record<string, string | number | undefined>,
  headers?: Record<string, string>,
  titre?: string,
): Promise<void> {
  const response = await http.get(url, { params, headers, responseType: 'blob' })
  const blobUrl = URL.createObjectURL(response.data as Blob)

  useDocumentPreviewStore.getState().open(blobUrl, titre)
}
