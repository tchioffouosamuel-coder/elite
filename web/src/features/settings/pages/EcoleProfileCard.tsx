import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Save } from 'lucide-react'
import { fetchEcole, updateEcole, type EcoleProfilePayload } from '@/features/settings/api'
import { fetchNiveaux } from '@/features/classes/api'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

export function EcoleProfileCard() {
  const { t, i18n } = useTranslation()
  const isFr = i18n.language === 'fr'
  const queryClient = useQueryClient()

  const { data: ecole, isLoading } = useQuery({ queryKey: ['ecole'], queryFn: fetchEcole })
  const { data: niveaux } = useQuery({ queryKey: ['niveaux'], queryFn: fetchNiveaux })

  const [form, setForm] = useState<EcoleProfilePayload>({ name: '', niveau_ids: [] })
  const [submitting, setSubmitting] = useState(false)
  const [saved, setSaved] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  useEffect(() => {
    if (ecole) {
      setForm({
        name: ecole.name,
        address: ecole.address ?? '',
        phone: ecole.phone ?? '',
        email: ecole.email ?? '',
        header_fr: ecole.header_fr ?? '',
        header_en: ecole.header_en ?? '',
        niveau_ids: ecole.niveau_ids,
      })
    }
  }, [ecole])

  const toggleNiveau = (id: number) => {
    setForm((f) => ({
      ...f,
      niveau_ids: f.niveau_ids.includes(id) ? f.niveau_ids.filter((n) => n !== id) : [...f.niveau_ids, id],
    }))
  }

  const handleSave = async () => {
    setServerError(null)
    setSubmitting(true)
    setSaved(false)
    try {
      await updateEcole(form)
      setSaved(true)
      queryClient.invalidateQueries({ queryKey: ['ecole'] })
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  if (isLoading || !ecole) return <Spinner />

  return (
    <Card>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="font-display text-base font-bold tracking-tight text-navy-800">{t('settings.ecole')}</h2>
        <Button size="sm" onClick={handleSave} disabled={submitting}>
          <Save className="h-4 w-4" />
          {t('common.save')}
        </Button>
      </div>
      {saved && <p className="mb-3 text-sm text-green-600">{t('settings.profil_saved')}</p>}
      {serverError && <p className="mb-3 text-sm text-red-500">{serverError}</p>}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input
          label={t('settings.nom')}
          value={form.name}
          onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
        />
        <Input
          label={t('settings.adresse')}
          value={form.address ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
        />
        <Input
          label={t('settings.telephone')}
          value={form.phone ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
        />
        <Input
          label={t('settings.email')}
          type="email"
          value={form.email ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
        />
        <label className="flex flex-col gap-1.5 sm:col-span-2">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('settings.header_fr')}</span>
          <textarea
            rows={2}
            value={form.header_fr ?? ''}
            onChange={(e) => setForm((f) => ({ ...f, header_fr: e.target.value }))}
            className="w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
          />
        </label>
        <label className="flex flex-col gap-1.5 sm:col-span-2">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('settings.header_en')}</span>
          <textarea
            rows={2}
            value={form.header_en ?? ''}
            onChange={(e) => setForm((f) => ({ ...f, header_en: e.target.value }))}
            className="w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
          />
        </label>
      </div>

      <div className="mt-5 border-t border-navy-100 pt-4">
        <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('settings.niveaux_operes')}</span>
        <p className="mb-3 mt-1 text-xs text-navy-400">{t('settings.niveaux_hint')}</p>
        <div className="flex flex-wrap gap-3">
          {niveaux?.map((n) => (
            <label
              key={n.id}
              className="flex cursor-pointer items-center gap-2 rounded-xl border border-navy-200 bg-white px-3.5 py-2 text-sm shadow-soft"
            >
              <input
                type="checkbox"
                className="h-4 w-4 rounded border-navy-300"
                checked={form.niveau_ids.includes(n.id)}
                onChange={() => toggleNiveau(n.id)}
              />
              {isFr ? n.name_fr : n.name_en}
            </label>
          ))}
        </div>
      </div>
    </Card>
  )
}
