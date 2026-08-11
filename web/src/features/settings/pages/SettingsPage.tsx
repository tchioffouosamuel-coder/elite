import { useState, useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Save, SlidersHorizontal, Info } from 'lucide-react'
import { fetchSettings, updateSettings } from '@/features/settings/api'
import { EcoleProfileCard, type EcoleProfileHandle } from '@/features/settings/pages/EcoleProfileCard'
import { EcoleImagesCard } from '@/features/settings/pages/EcoleImagesCard'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { useAuthStore } from '@/shared/store/authStore'
import { Spinner } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const GROUP_LABELS: Record<string, { fr: string; en: string }> = {
  evaluations: { fr: 'Évaluations', en: 'Grading' },
  palmares: { fr: 'Palmarès', en: 'Honor roll' },
  mentions: { fr: 'Mentions & appréciations', en: 'Remarks & mentions' },
  examens: { fr: 'Examens officiels', en: 'Official examinations' },
}

export function SettingsPage() {
  const ecoleActive = useAuthStore((s) => s.activeSchool())
  const { t, i18n } = useTranslation()
  const isFr = i18n.language === 'fr'
  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery({ queryKey: ['settings'], queryFn: fetchSettings })
  const [valeurs, setValeurs] = useState<Record<string, string | number>>({})
  const [submitting, setSubmitting] = useState(false)
  const ecoleRef = useRef<EcoleProfileHandle>(null)

  useEffect(() => {
    if (data) setValeurs(Object.fromEntries(data.map((s) => [s.key, s.value])))
  }, [data])

  // Un seul bouton pour toute la page : deux "Enregistrer" séparés (celui-ci
  // + celui de la carte Établissement) faisaient croire que tout était
  // sauvegardé alors que seule la moitié du formulaire partait réellement.
  const handleSave = async () => {
    setSubmitting(true)
    try {
      await Promise.all([updateSettings(valeurs), ecoleRef.current?.save()])
      queryClient.invalidateQueries({ queryKey: ['settings'] })
      succes(t('settings.saved'))
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  if (isLoading || !data) return <Spinner />

  const groupes = [...new Set(data.map((s) => s.groupe))]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('settings.title')}
        sousTitre={
          ecoleActive
            ? `Ces réglages ne valent que pour ${ecoleActive.name}.`
            : 'Ces réglages sont propres à l\'établissement actif.'
        }
        icon={SlidersHorizontal}
        actions={
          <Button onClick={handleSave} disabled={submitting}>
            <Save className="h-4 w-4" />
            {t('common.save')}
          </Button>
        }
      />

      {/* Chaque école du complexe a ses propres seuils : le rappeler évite de
          croire qu'on règle le complexe entier depuis cet écran. */}
      <Card className="flex items-start gap-3 border-gold-100 bg-gold-50/50">
        <Info className="mt-0.5 h-4 w-4 flex-none text-gold-600" />
        <p className="text-sm text-navy-600">
          Les paramètres, le profil et les images ci-dessous appartiennent à{' '}
          <strong className="text-navy-800">{ecoleActive?.name ?? "l'établissement actif"}</strong>. Basculez
          d'établissement depuis le sélecteur en haut de page pour régler les autres écoles du complexe.
        </p>
      </Card>

      <EcoleProfileCard ref={ecoleRef} />

      <EcoleImagesCard />

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
                      type={s.type === 'text' ? 'text' : 'number'}
                      step={s.type === 'text' ? undefined : '0.5'}
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
