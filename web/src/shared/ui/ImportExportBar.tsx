import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Upload } from 'lucide-react'
import { Button } from '@/shared/ui/Button'
import { ImportModal } from '@/shared/ui/ImportModal'
import { ExportButton } from '@/shared/ui/ExportButton'
import { TemplateDownloadButton } from '@/shared/ui/TemplateDownloadButton'

/**
 * Les trois actions standard d'un modèle « maître » (Importer / Exporter /
 * Télécharger le modèle), à déposer telles quelles dans le `actions` d'un
 * `PageHeader` — un seul endroit à modifier si leur agencement doit changer
 * partout à la fois. `nomFichier` sans extension (ex. `niveaux`), les trois
 * URLs suivent la convention `{model}/import`, `{model}/export`, `{model}/modele`.
 */
export function ImportExportBar({
  titreImport,
  importUrl,
  exportUrl,
  modeleUrl,
  colonnes,
  nomFichier,
  onImported,
}: {
  titreImport: string
  importUrl: string
  exportUrl: string
  modeleUrl: string
  colonnes: string[]
  nomFichier: string
  onImported: () => void
}) {
  const { t } = useTranslation()
  const [importOuvert, setImportOuvert] = useState(false)

  return (
    <>
      <TemplateDownloadButton url={modeleUrl} nomFichier={`modele-${nomFichier}.xlsx`} />
      <ExportButton url={exportUrl} nomFichier={`${nomFichier}.xlsx`} />
      <Button type="button" variant="secondary" onClick={() => setImportOuvert(true)}>
        <Upload className="h-4 w-4" />
        {t('import.submit')}
      </Button>

      {importOuvert && (
        <ImportModal
          title={titreImport}
          url={importUrl}
          columns={colonnes}
          onClose={() => setImportOuvert(false)}
          onImported={() => {
            setImportOuvert(false)
            onImported()
          }}
        />
      )}
    </>
  )
}
