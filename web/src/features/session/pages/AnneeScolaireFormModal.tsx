import { useForm } from 'react-hook-form'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Modal } from '@/shared/ui/Modal'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { createAnneeScolaire, updateAnneeScolaire, type AnneeScolaire, type AnneeScolairePayload } from '@/features/session/api'
import type { ApiError } from '@/shared/types/api'

export function AnneeScolaireFormModal({
  edition,
  onClose,
  onCreated,
}: {
  /** Présente en mode édition : préremplit le formulaire et bascule vers la mise à jour. */
  edition?: AnneeScolaire
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
  } = useForm<AnneeScolairePayload & { is_active: boolean }>({
    defaultValues: edition
      ? { libelle: edition.libelle, date_debut: edition.date_debut, date_fin: edition.date_fin, is_active: edition.is_active }
      : { is_active: false },
  })

  const onSubmit = async (values: AnneeScolairePayload & { is_active: boolean }) => {
    setServerError(null)
    setSubmitting(true)
    try {
      if (edition) {
        await updateAnneeScolaire(edition.id, values)
      } else {
        await createAnneeScolaire(values)
      }
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={edition ? t('session.edit_annee') : t('session.add_annee')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('session.libelle')}
          placeholder="2026-2027"
          error={errors.libelle?.message}
          {...register('libelle', { required: true })}
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
        {!edition && (
          <label className="flex items-center gap-2 text-sm text-navy-700">
            <input type="checkbox" className="h-4 w-4 rounded border-navy-300" {...register('is_active')} />
            {t('session.active')}
          </label>
        )}

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
