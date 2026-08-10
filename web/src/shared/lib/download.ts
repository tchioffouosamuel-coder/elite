import { http } from '@/shared/lib/http'

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
 * Ouvre un PDF dans un nouvel onglet (plutôt que de le télécharger) : on
 * ouvre l'onglet vide tout de suite, dans le geste utilisateur, pour éviter
 * le blocage de pop-up, puis on y charge le PDF récupéré en blob une fois
 * la requête authentifiée terminée.
 */
export async function ouvrirDocument(url: string, params?: Record<string, string | number | undefined>): Promise<void> {
  const fenetre = window.open('', '_blank')

  const response = await http.get(url, { params, responseType: 'blob' })
  const blobUrl = URL.createObjectURL(response.data as Blob)

  if (fenetre) {
    fenetre.location.href = blobUrl
  } else {
    window.open(blobUrl, '_blank')
  }
}
