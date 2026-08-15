import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Upload } from 'lucide-react'
import { http } from '@/shared/lib/http'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import type { ApiError } from '@/shared/types/api'

interface ImportResult {
  imported: number
  failed: number
  /** Renseignés uniquement par les imports qui savent réactualiser des lignes existantes (élèves). */
  updated?: number
  ignored?: number
  classes_introuvables?: Record<string, number>
}

export function ImportModal({
  title,
  url,
  columns,
  extraFields,
  onClose,
  onImported,
}: {
  title: string
  url: string
  columns: string[]
  extraFields?: Record<string, string | number>
  onClose: () => void
  onImported: () => void
}) {
  const { t } = useTranslation()
  const [file, setFile] = useState<File | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [result, setResult] = useState<ImportResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async () => {
    if (!file) return
    setSubmitting(true)
    setError(null)
    try {
      const formData = new FormData()
      formData.append('file', file)
      Object.entries(extraFields ?? {}).forEach(([key, value]) => formData.append(key, String(value)))

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
        <div className="rounded-lg bg-cream-100 p-3 text-xs text-navy-500">
          <p className="mb-1 font-semibold">{t('import.template_hint')}</p>
          <code className="text-navy-700">{columns.join(', ')}</code>
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
        {result && (
          <div className="flex flex-col gap-1.5 text-sm">
            <p className="text-green-600">
              {t('import.result', { imported: result.imported, failed: result.failed })}
              {result.updated ? ` ${t('import.updated', { count: result.updated })}` : ''}
            </p>
            {!!result.ignored && <p className="text-navy-500">{t('import.ignored', { count: result.ignored })}</p>}
            {result.classes_introuvables && Object.keys(result.classes_introuvables).length > 0 && (
              <p className="text-amber-600">
                {t('import.classes_introuvables')}{' '}
                <span className="font-semibold">
                  {Object.entries(result.classes_introuvables)
                    .map(([nom, total]) => `${nom} (${total})`)
                    .join(', ')}
                </span>
              </p>
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
