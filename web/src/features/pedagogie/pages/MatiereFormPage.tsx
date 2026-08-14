import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useParams, Link } from 'react-router-dom'
import { useState } from 'react'
import { ArrowLeft, BookOpen } from 'lucide-react'
import { fetchMatieres, createMatiere, updateMatiere, type MatierePayload } from '@/features/pedagogie/api'
import { fetchDepartements } from '@/features/personnel/api'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { estSecondaire } from '@/shared/lib/ecole'
import { succes, erreur } from '@/shared/lib/alertes'
import { LIBELLES_COMPOSANTES, type Composante } from '@/features/primaire/api'
import type { ApiError } from '@/shared/types/api'

/** Volets systématiques, plus « pratique » si la matière l'évalue. */
function composantesActives(evaluePratique: boolean): Composante[] {
  return evaluePratique ? ['oral', 'ecrit', 'savoir_etre', 'pratique'] : ['oral', 'ecrit', 'savoir_etre']
}

export function MatiereFormPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { id } = useParams<{ id: string }>()
  const matiereId = id ? Number(id) : undefined

  const { data: matieres, isLoading } = useQuery({ queryKey: ['matieres'], queryFn: fetchMatieres })
  const { data: departements } = useQuery({ queryKey: ['departements'], queryFn: fetchDepartements })
  const matiere = matiereId ? matieres?.find((m) => m.id === matiereId) : undefined

  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  // Au primaire une matière est notée sur un barème propre et se découpe en
  // volets ; au secondaire elle est sur 20 et relève d'un département.
  const secondaire = estSecondaire()

  const { register, handleSubmit, watch } = useForm<MatierePayload>({
    defaultValues: matiere
      ? {
          nom: matiere.nom,
          nom_en: matiere.nom_en ?? '',
          abbreviation: matiere.abbreviation ?? '',
          departement_id: matiere.departement?.id ?? null,
          notation: matiere.notation ?? null,
          evalue_pratique: matiere.composantes.includes('pratique'),
          repartition_volets: matiere.repartition_volets,
        }
      : undefined,
  })

  const notationSaisie = Number(watch('notation')) || 0
  const evaluePratique = !!watch('evalue_pratique')
  const composantesForm = composantesActives(evaluePratique)
  const repartitionSaisie = watch('repartition_volets')
  const sommeVolets = composantesForm.reduce(
    (total, c) => total + (Number(repartitionSaisie?.[c]) || 0),
    0,
  )
  const repartitionValide = !secondaire && notationSaisie > 0 && Math.abs(sommeVolets - notationSaisie) < 0.01

  const onSubmit = async (values: MatierePayload) => {
    if (!secondaire && !repartitionValide) return

    setServerError(null)
    setSubmitting(true)
    try {
      const payload: MatierePayload = {
        ...values,
        departement_id: values.departement_id ? Number(values.departement_id) : null,
        notation: values.notation ? Number(values.notation) : null,
        repartition_volets: secondaire
          ? null
          : Object.fromEntries(composantesForm.map((c) => [c, Number(values.repartition_volets?.[c]) || 0])),
      }

      if (matiere) {
        await updateMatiere(matiere.id, payload)
      } else {
        await createMatiere(payload)
      }
      queryClient.invalidateQueries({ queryKey: ['matieres'] })
      succes(matiere ? 'Matière mise à jour.' : 'Matière créée.')
      navigate('/matieres')
    } catch (err) {
      setServerError((err as ApiError).message)
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  if (matiereId && isLoading) return <Spinner />
  if (matiereId && !isLoading && !matiere) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to="/matieres" className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </Link>
        <div className="flex items-center gap-3">
          <span className="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-linear-to-br from-gold-50 to-gold-100 shadow-soft ring-1 ring-gold-100">
            <BookOpen className="h-5 w-5 text-gold-600" />
          </span>
          <h1 className="font-display text-xl font-bold tracking-tight text-navy-900 sm:text-2xl">
            {matiere ? t('matieres.edit') : t('matieres.add')}
          </h1>
        </div>
      </div>

      <Card className="max-w-2xl p-5">
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <Input label={t('matieres.nom')} {...register('nom', { required: true })} />
          <Input label={t('matieres.abbreviation')} {...register('abbreviation')} />
          {secondaire ? (
            <Select label={t('personnel.departement')} {...register('departement_id')}>
              <option value="">—</option>
              {departements?.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.nom}
                </option>
              ))}
            </Select>
          ) : (
            <>
              <Input label={t('matieres.nom_en')} {...register('nom_en')} />
              <Input
                type="number"
                min={10}
                max={100}
                label={t('matieres.notation')}
                {...register('notation', { required: true })}
              />
              <label className="flex items-center gap-2 text-sm text-navy-700">
                <input type="checkbox" className="h-4 w-4 rounded border-navy-300" {...register('evalue_pratique')} />
                {t('matieres.evalue_pratique')}
              </label>

              <div className="flex flex-col gap-2 rounded-xl border border-navy-100 bg-cream-50/60 p-3">
                <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
                  {t('matieres.repartition_volets')}
                </span>
                <div className="grid grid-cols-2 gap-3">
                  {composantesForm.map((composante) => (
                    <Input
                      key={composante}
                      type="number"
                      min={0}
                      step={0.5}
                      label={LIBELLES_COMPOSANTES[composante]}
                      {...register(`repartition_volets.${composante}`, { required: true, min: 0 })}
                    />
                  ))}
                </div>
                <span className={`text-xs font-medium ${repartitionValide ? 'text-green-600' : 'text-red-500'}`}>
                  {t('matieres.repartition_somme', { somme: sommeVolets, notation: notationSaisie })}
                </span>
              </div>
            </>
          )}

          {serverError && <p className="text-sm text-red-500">{serverError}</p>}

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => navigate('/matieres')}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={submitting || (!secondaire && !repartitionValide)}>
              {t('common.save')}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  )
}
