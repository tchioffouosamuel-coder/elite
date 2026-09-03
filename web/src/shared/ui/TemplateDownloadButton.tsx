import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FileDown } from 'lucide-react'
import { telechargerFichier } from '@/shared/lib/download'
import { Button } from '@/shared/ui/Button'
import { erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Bouton « Télécharger le modèle » générique : un classeur vierge avec
 * uniquement les en-têtes attendues par l'import — même mécanique que
 * {@see ExportButton}, vers l'endpoint `{model}/modele`.
 */
export function TemplateDownloadButton({
  url,
  params,
  nomFichier,
}: {
  url: string
  params?: Record<string, string | number | undefined>
  nomFichier: string
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
      <FileDown className="h-4 w-4" />
      {telechargement ? t('export.telechargement') : t('import.telecharger_modele')}
    </Button>
  )
}
