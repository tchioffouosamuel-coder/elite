import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Download } from 'lucide-react'
import { telechargerFichier } from '@/shared/lib/download'
import { Button } from '@/shared/ui/Button'
import { erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Bouton « Exporter » générique : télécharge le fichier Excel renvoyé par
 * `url` via {@see telechargerFichier} — même mécanique que le bouton export
 * déjà câblé à la main sur Élèves/Personnel, réutilisable sans dupliquer la
 * gestion du blob et du nom de fichier.
 */
export function ExportButton({
  url,
  params,
  nomFichier,
  label,
}: {
  url: string
  params?: Record<string, string | number | undefined>
  nomFichier: string
  label?: string
}) {
  const { t } = useTranslation()
  const [telechargement, setTelechargement] = useState(false)

  const onClick = async () => {
    setTelechargement(true)
    try {
      await telechargerFichier(url, params, nomFichier)
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setTelechargement(false)
    }
  }

  return (
    <Button type="button" variant="secondary" onClick={onClick} disabled={telechargement}>
      <Download className="h-4 w-4" />
      {telechargement ? t('export.telechargement') : (label ?? t('export.excel'))}
    </Button>
  )
}
