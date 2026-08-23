import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Upload } from 'lucide-react'
import { http } from '@/shared/lib/http'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Select } from '@/shared/ui/Field'
import type { ApiError } from '@/shared/types/api'

interface ImportResult {
  imported: number
  failed: number
  /** Renseignés uniquement par les imports qui savent réactualiser des lignes existantes. */
  updated?: number
  ignored?: number
  /** Accès de connexion ouverts par l'import (personnel). */
  comptes_ouverts?: number
  /** Affectations classe ↔ matière rattachées au passage (import des matières). */
  affectations?: number
  /** Dettes antérieures reprises depuis le fichier de situation (import des élèves). */
  dettes?: number
  dettes_montant?: number
  dettes_ignorees?: number
  /** Libellés que l'import n'a pas su rattacher, avec le nombre de lignes concernées. */
  classes_introuvables?: Record<string, number>
  enseignants_introuvables?: Record<string, number>
  affectations_non_rattachees?: Record<string, number>
}

interface ImportProgress {
  processed: number
  total: number
  current_name: string | null
}

/**
 * Choix que l'utilisateur pose avant d'envoyer le fichier, quand celui-ci ne
 * suffit pas à le déduire — le cycle d'un catalogue de matières, par exemple :
 * un fichier de secondaire et un fichier de primaire se ressemblent trop pour
 * qu'on devine lequel on lit. Chaque option annonce les colonnes qu'elle
 * attend, l'aide affichée suivant la sélection.
 */
export interface ChoixImport {
  nom: string
  label: string
  defaut: string
  options: { valeur: string; libelle: string; colonnes?: string[] }[]
}

export function ImportModal({
  title,
  url,
  columns,
  choix,
  extraFields,
  progressUrl,
  onClose,
  onImported,
}: {
  title: string
  url: string
  columns: string[]
  choix?: ChoixImport
  extraFields?: Record<string, string | number>
  progressUrl?: string
  onClose: () => void
  onImported: () => void
}) {
  const { t } = useTranslation()
  const [file, setFile] = useState<File | null>(null)
  const [choisi, setChoisi] = useState(choix?.defaut ?? '')
  const [submitting, setSubmitting] = useState(false)
  const [result, setResult] = useState<ImportResult | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [progress, setProgress] = useState<ImportProgress | null>(null)
  const [progressToken, setProgressToken] = useState<string | null>(null)

  useEffect(() => {
    if (!submitting || !progressUrl || !progressToken) return
    const timer = window.setInterval(() => {
      void http.get<{ data: ImportProgress }>(`${progressUrl}/${progressToken}`)
        .then(({ data }) => setProgress(data.data))
        .catch(() => undefined)
    }, 700)

    return () => window.clearInterval(timer)
  }, [progressToken, progressUrl, submitting])

  // Les colonnes attendues dépendent du choix : afficher celles du primaire à
  // qui importe un fichier de secondaire l'enverrait corriger le mauvais.
  const colonnesAttendues = choix?.options.find((o) => o.valeur === choisi)?.colonnes ?? columns

  const handleSubmit = async () => {
    if (!file) return
    setSubmitting(true)
    setError(null)
    const token = progressUrl ? crypto.randomUUID() : null
    setProgressToken(token)
    setProgress(progressUrl ? { processed: 0, total: 0, current_name: null } : null)
    try {
      const formData = new FormData()
      formData.append('file', file)
      if (choix) formData.append(choix.nom, choisi)
      Object.entries(extraFields ?? {}).forEach(([key, value]) => formData.append(key, String(value)))
      if (token) formData.append('progress_token', token)

      const { data } = await http.post<{ data: ImportResult }>(url, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setResult(data.data)
      onImported()
    } catch (err) {
      setError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={title} onClose={onClose}>
      <div className="flex flex-col gap-4">
        {choix && (
          <Select label={choix.label} value={choisi} onChange={(e) => setChoisi(e.target.value)}>
            {choix.options.map((option) => (
              <option key={option.valeur} value={option.valeur}>
                {option.libelle}
              </option>
            ))}
          </Select>
        )}

        <div className="rounded-lg bg-cream-100 p-3 text-xs text-navy-500">
          <p className="mb-1 font-semibold">{t('import.template_hint')}</p>
          <code className="text-navy-700">{colonnesAttendues.join(', ')}</code>
        </div>

        <label className="flex flex-col gap-1.5">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('import.file')}</span>
          <input
            type="file"
            accept=".xlsx,.xls,.csv"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            className="w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm shadow-soft file:mr-3 file:rounded-lg file:border-0 file:bg-navy-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-cream-50"
          />
        </label>

        {error && <p className="text-sm text-red-500">{error}</p>}
        {submitting && progress && (
          <div className="rounded-lg border border-navy-100 bg-cream-50 p-3" aria-live="polite">
            <div className="mb-2 flex items-center justify-between gap-3 text-xs font-semibold text-navy-600">
              <span className="truncate">{progress.current_name ?? t('import.processing')}</span>
              <span className="flex-none">{progress.total > 0 ? `${progress.processed}/${progress.total}` : '…'}</span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-navy-100">
              <div
                className="h-full rounded-full bg-gold-500 transition-[width] duration-300"
                style={{ width: `${progress.total > 0 ? Math.min(100, progress.processed * 100 / progress.total) : 8}%` }}
              />
            </div>
          </div>
        )}
        {result && (
          <div className="flex flex-col gap-1.5 text-sm">
            <p className="text-green-600">
              {t('import.result', { imported: result.imported, failed: result.failed })}
              {result.updated ? ` ${t('import.updated', { count: result.updated })}` : ''}
            </p>
            {!!result.affectations && (
              <p className="text-navy-500">{t('import.affectations', { count: result.affectations })}</p>
            )}
            {!!result.dettes && (
              <p className="text-navy-500">
                {t('import.dettes', { count: result.dettes, montant: result.dettes_montant?.toLocaleString('fr-FR') })}
              </p>
            )}
            {!!result.dettes_ignorees && (
              <p className="text-navy-400">{t('import.dettes_ignorees', { count: result.dettes_ignorees })}</p>
            )}
            {!!result.ignored && <p className="text-navy-500">{t('import.ignored', { count: result.ignored })}</p>}
            {!!result.comptes_ouverts && (
              <p className="text-navy-500">{t('import.comptes_ouverts', { count: result.comptes_ouverts })}</p>
            )}
            {(
              [
                ['import.classes_introuvables', result.classes_introuvables],
                ['import.enseignants_introuvables', result.enseignants_introuvables],
                ['import.affectations_non_rattachees', result.affectations_non_rattachees],
              ] as const
            ).map(([cle, libelles]) =>
              libelles && Object.keys(libelles).length > 0 ? (
                <p key={cle} className="text-amber-600">
                  {t(cle)}{' '}
                  <span className="font-semibold">
                    {Object.entries(libelles)
                      .map(([nom, total]) => `${nom} (${total})`)
                      .join(', ')}
                  </span>
                </p>
              ) : null,
            )}
          </div>
        )}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={handleSubmit} disabled={!file || submitting}>
            <Upload className="h-4 w-4" />
            {t('import.submit')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
