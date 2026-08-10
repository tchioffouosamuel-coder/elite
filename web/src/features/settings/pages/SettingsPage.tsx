import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Save } from 'lucide-react'
import { fetchSettings, updateSettings } from '@/features/settings/api'
import { EcoleProfileCard } from '@/features/settings/pages/EcoleProfileCard'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Spinner } from '@/shared/ui/Feedback'

const GROUP_LABELS: Record<string, { fr: string; en: string }> = {
  evaluations: { fr: 'Évaluations', en: 'Grading' },
  palmares: { fr: 'Palmarès', en: 'Honor roll' },
  mentions: { fr: 'Mentions & appréciations', en: 'Remarks & mentions' },
}

export function SettingsPage() {
  const { t, i18n } = useTranslation()
  const isFr = i18n.language === 'fr'
  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery({ queryKey: ['settings'], queryFn: fetchSettings })
  const [valeurs, setValeurs] = useState<Record<string, string | number>>({})
  const [submitting, setSubmitting] = useState(false)
  const [saved, setSaved] = useState(false)

  useEffect(() => {
    if (data) setValeurs(Object.fromEntries(data.map((s) => [s.key, s.value])))
  }, [data])

  const handleSave = async () => {
    setSubmitting(true)
    setSaved(false)
    try {
      await updateSettings(valeurs)
      setSaved(true)
      queryClient.invalidateQueries({ queryKey: ['settings'] })
    } finally {
      setSubmitting(false)
    }
  }

  if (isLoading || !data) return <Spinner />

  const groupes = [...new Set(data.map((s) => s.groupe))]

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('settings.title')}</h1>
        <Button onClick={handleSave} disabled={submitting}>
          <Save className="h-4 w-4" />
          {t('common.save')}
        </Button>
      </div>
      {saved && <p className="text-sm text-green-600">{t('settings.saved')}</p>}

      <EcoleProfileCard />

      {groupes.map((groupe) => (
        <Card key={groupe}>
          <h2 className="mb-4 font-display text-base font-bold tracking-tight text-navy-800">
            {isFr ? GROUP_LABELS[groupe]?.fr : GROUP_LABELS[groupe]?.en ?? groupe}
          </h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {data
              .filter((s) => s.groupe === groupe)
              .map((s) => (
                <label key={s.key} className="flex flex-col gap-1.5">
                  <span className="text-xs font-semibold text-navy-500">{isFr ? s.label_fr : s.label_en}</span>
                  {s.type === 'select' ? (
                    <select
                      value={valeurs[s.key] ?? ''}
                      onChange={(e) => setValeurs((v) => ({ ...v, [s.key]: e.target.value }))}
                      className="w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
                    >
                      {s.options?.map((opt) => (
                        <option key={opt} value={opt}>
                          {opt}
                        </option>
                      ))}
                    </select>
                  ) : (
                    <input
                      type="number"
                      step="0.5"
                      value={valeurs[s.key] ?? ''}
                      onChange={(e) => setValeurs((v) => ({ ...v, [s.key]: e.target.value }))}
                      className="w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
                    />
                  )}
                </label>
              ))}
          </div>
        </Card>
      ))}
    </div>
  )
}
