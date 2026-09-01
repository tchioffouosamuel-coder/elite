import { useForm } from 'react-hook-form'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Modal } from '@/shared/ui/Modal'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { createTrimestre, updateTrimestre, type TrimestrePayload } from '@/features/session/api'
import type { Trimestre } from '@/features/pedagogie/api'
import type { ApiError } from '@/shared/types/api'

export function TrimestreFormModal({
  anneeScolaireId,
  prochainOrdre,
  edition,
  onClose,
  onCreated,
}: {
  anneeScolaireId: number
  prochainOrdre: number
  /** Présente en mode édition : préremplit le formulaire et bascule vers la mise à jour. */
  edition?: Trimestre
  onClose: () => void
  onCreated: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<TrimestrePayload>({
    defaultValues: edition
      ? { annee_scolaire_id: anneeScolaireId, libelle: edition.libelle, ordre: edition.ordre, date_debut: edition.date_debut ?? '', date_fin: edition.date_fin ?? '' }
      : { annee_scolaire_id: anneeScolaireId, ordre: prochainOrdre, libelle: `Trimestre ${prochainOrdre}` },
  })

  const onSubmit = async (values: TrimestrePayload) => {
    setServerError(null)
    setSubmitting(true)
    try {
      if (edition) {
        await updateTrimestre(edition.id, { ...values, ordre: Number(values.ordre) })
      } else {
        await createTrimestre({ ...values, annee_scolaire_id: anneeScolaireId, ordre: Number(values.ordre) })
      }
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={edition ? t('session.edit_trimestre') : t('session.add_trimestre')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input label={t('session.libelle')} error={errors.libelle?.message} {...register('libelle', { required: true })} />
        <Input
          type="number"
          min={1}
          max={3}
          label={t('session.ordre')}
          error={errors.ordre?.message}
          {...register('ordre', { required: true, min: 1, max: 3 })}
        />
        <div className="grid grid-cols-2 gap-3">
          <Input
            type="date"
            label={t('session.date_debut')}
            error={errors.date_debut?.message}
            {...register('date_debut', { required: true })}
          />
          <Input
            type="date"
            label={t('session.date_fin')}
            error={errors.date_fin?.message}
            {...register('date_fin', { required: true })}
          />
        </div>

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={submitting}>
            {t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
