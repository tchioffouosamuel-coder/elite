import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useParams, Link } from 'react-router-dom'
import { useState } from 'react'
import { ArrowLeft, BookOpen } from 'lucide-react'
import {
  fetchCompetences,
  fetchMatieres,
  createMatiere,
  updateMatiere,
  type MatierePayload,
} from '@/features/pedagogie/api'
import { fetchDepartements } from '@/features/personnel/api'
import { fetchSchools } from '@/features/classes/api'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { estSecondaire } from '@/shared/lib/ecole'
import { succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Création et modification d'une matière.
 *
 * Au primaire et en maternelle, la matière ne porte plus ni barème ni volets :
 * ils appartiennent à la compétence dont elle relève, et c'est cette compétence
 * que le bulletin note. Le formulaire ne demande donc plus qu'un rattachement.
 */
export function MatiereFormPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { id } = useParams<{ id: string }>()
  const matiereId = id ? Number(id) : undefined

  const { data: matieres, isLoading } = useQuery({ queryKey: ['matieres'], queryFn: fetchMatieres })
  const { data: departements } = useQuery({ queryKey: ['departements'], queryFn: fetchDepartements })
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
  const { data: competences } = useQuery({ queryKey: ['competences'], queryFn: fetchCompetences })
  const matiere = matiereId ? matieres?.find((m) => m.id === matiereId) : undefined

  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const { register, handleSubmit, watch, formState: { errors } } = useForm<MatierePayload>({
    defaultValues: matiere
      ? {
        nom: matiere.nom,
        nom_en: matiere.nom_en ?? '',
        abbreviation: matiere.abbreviation ?? '',
        departement_id: matiere.departement?.id ?? null,
        school_id: matiere.school_id ?? null,
        competence_id: matiere.competence_id ?? null,
      }
      : undefined,
  })

  const ecoleChoisie = watch('school_id')
  const departementsFiltres = ecoleChoisie
    ? departements?.filter((d) => d.school_id === Number(ecoleChoisie))
    : departements

  // Au primaire la matière relève d'une compétence ; au secondaire elle relève
  // d'un département et se note elle-même. Le type vient de l'école choisie
  // dans ce formulaire (ou, en édition, de celle de la matière) — pas de
  // l'école active globale, sans valeur unique en mode agrégé.
  const typeEcoleFormulaire = ecoleChoisie
    ? schools?.find((s) => s.id === Number(ecoleChoisie))?.type
    : matiere?.school?.type
  const secondaire = estSecondaire(typeEcoleFormulaire)

  const competencesDisponibles = (competences ?? []).filter(
    (competence) => !ecoleChoisie || competence.school_id === Number(ecoleChoisie),
  )

  const onSubmit = async (values: MatierePayload) => {
    setServerError(null)
    setSubmitting(true)
    try {
      const payload: MatierePayload = {
        ...values,
        departement_id: values.departement_id ? Number(values.departement_id) : null,
        school_id: values.school_id ? Number(values.school_id) : null,
        // Le secondaire ne connaît pas les compétences : y laisser une valeur
        // rattacherait la matière à un bloc qui ne la notera jamais.
        competence_id: secondaire || !values.competence_id ? null : Number(values.competence_id),
      }

      if (matiere) {
        await updateMatiere(matiere.id, payload)
      } else {
        await createMatiere(payload)
      }
      queryClient.invalidateQueries({ queryKey: ['matieres'] })
      queryClient.invalidateQueries({ queryKey: ['competences'] })
      succes(matiere ? t('matieres.updated') : t('matieres.created'))
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
          {!matiere && (schools?.length ?? 0) > 1 && (
            <Select
              label={`${t('classes.ecole')} *`}
              error={errors.school_id?.message}
              {...register('school_id', { required: "L'école est requise." })}
            >
              <option value="">—</option>
              {schools?.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          )}
          <Input label={t('matieres.nom')} {...register('nom', { required: true })} />
          <Input label={t('matieres.abbreviation')} {...register('abbreviation')} />
          {secondaire ? (
            <Select label={t('personnel.departement')} {...register('departement_id')}>
              <option value="">—</option>
              {departementsFiltres?.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.nom}
                </option>
              ))}
            </Select>
          ) : (
            <>
              <Input label={t('matieres.nom_en')} {...register('nom_en')} />

              <Select
                label={t('competences.rattachement')}
                error={errors.competence_id?.message}
                {...register('competence_id', { required: t('competences.rattachement_requis') })}
              >
                <option value="">—</option>
                {competencesDisponibles.map((competence) => (
                  <option key={competence.id} value={competence.id}>
                    {competence.label_fr} ({t('competences.sur_bareme', { bareme: competence.notation })})
                  </option>
                ))}
              </Select>

              <p className="rounded-xl border border-navy-100 bg-cream-50/60 px-3.5 py-2.5 text-xs text-navy-500">
                {t('competences.aide_matiere')}
              </p>

              {competencesDisponibles.length === 0 && (
                <p className="text-xs font-semibold text-gold-600">{t('competences.aucune_pour_ecole')}</p>
              )}
            </>
          )}

          {serverError && <p className="text-sm text-red-500">{serverError}</p>}

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => navigate('/matieres')}>
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
