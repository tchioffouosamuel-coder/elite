import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useParams, Link } from 'react-router-dom'
import { ArrowLeft, HeartPulse } from 'lucide-react'
import {
  createVisiteInfirmerie,
  fetchVisitesInfirmerie,
  updateVisiteInfirmerie,
  type VisiteInfirmeriePayload,
} from '@/features/infirmerie/api'
import { fetchEleves } from '@/features/eleves/api'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Input, Select, FieldWrapper } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface VisiteFormValues {
  eleve_id: number
  date_visite: string
  raison: string
  soins_prodiges: string
  cout_soins?: number | string | null
  observations?: string | null
}

const champTexte =
  'w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm text-navy-900 shadow-soft transition-colors placeholder:text-navy-300 focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100'

function maintenantLocal(): string {
  const date = new Date()
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset())
  return date.toISOString().slice(0, 16)
}

export function VisiteInfirmerieFormPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { id } = useParams<{ id: string }>()
  const visiteId = id ? Number(id) : undefined

  const { data: visites, isLoading } = useQuery({
    queryKey: ['infirmerie', 'visites', {}],
    queryFn: () => fetchVisitesInfirmerie(),
  })
  const { data: eleves } = useQuery({ queryKey: ['eleves', 'infirmerie-form'], queryFn: () => fetchEleves({ per_page: 500 }) })
  const visite = visiteId ? visites?.find((v) => v.id === visiteId) : undefined

  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const { register, handleSubmit } = useForm<VisiteFormValues>({
    defaultValues: visite
      ? {
          eleve_id: visite.eleve.id,
          date_visite: visite.date_visite,
          raison: visite.raison,
          soins_prodiges: visite.soins_prodiges,
          cout_soins: visite.cout_soins,
          observations: visite.observations ?? '',
        }
      : {
          date_visite: maintenantLocal(),
          cout_soins: 0,
          observations: '',
        },
  })

  const onSubmit = async (values: VisiteFormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      const payload: VisiteInfirmeriePayload = {
        eleve_id: Number(values.eleve_id),
        date_visite: values.date_visite,
        raison: values.raison,
        soins_prodiges: values.soins_prodiges,
        cout_soins: values.cout_soins === '' || values.cout_soins == null ? 0 : Number(values.cout_soins),
        observations: values.observations?.trim() || null,
      }

      if (visite) {
        await updateVisiteInfirmerie(visite.id, payload)
        succes(t('infirmerie.updated'))
      } else {
        await createVisiteInfirmerie(payload)
        succes(t('infirmerie.created'))
      }

      queryClient.invalidateQueries({ queryKey: ['infirmerie', 'visites'] })
      navigate('/infirmerie')
    } catch (err) {
      setServerError((err as ApiError).message)
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  if (visiteId && isLoading) return <Spinner />
  if (visiteId && !isLoading && !visite) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to="/infirmerie" className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </Link>
        <div className="flex items-center gap-3">
          <span className="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-linear-to-br from-gold-50 to-gold-100 shadow-soft ring-1 ring-gold-100">
            <HeartPulse className="h-5 w-5 text-gold-600" />
          </span>
          <h1 className="font-display text-xl font-bold tracking-tight text-navy-900 sm:text-2xl">
            {visite ? t('infirmerie.edit_visit') : t('infirmerie.add_visit')}
          </h1>
        </div>
      </div>

      <Card className="max-w-2xl p-5">
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <Select label={t('eleves.title')} {...register('eleve_id', { required: true })}>
            <option value="">—</option>
            {eleves?.items.map((e) => (
              <option key={e.id} value={e.id}>
                {e.nom_complet} — {e.classe?.nom ?? '—'}
              </option>
            ))}
          </Select>

          <Input label={t('infirmerie.date_visite')} type="datetime-local" {...register('date_visite', { required: true })} />

          <FieldWrapper label={t('infirmerie.raison')}>
            <textarea rows={3} className={champTexte} {...register('raison', { required: true })} />
          </FieldWrapper>

          <FieldWrapper label={t('infirmerie.soins_prodiges')}>
            <textarea rows={3} className={champTexte} {...register('soins_prodiges', { required: true })} />
          </FieldWrapper>

          <Input label={t('infirmerie.cout_soins')} type="number" min={0} step={1} {...register('cout_soins')} />

          <FieldWrapper label={t('infirmerie.observations')}>
            <textarea rows={3} className={champTexte} {...register('observations')} />
          </FieldWrapper>

          {serverError && <p className="text-sm text-red-500">{serverError}</p>}

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => navigate('/infirmerie')}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={submitting}>
              {t('common.save')}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  )
}
