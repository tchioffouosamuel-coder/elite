import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { createFonctionReferentiel, updateFonctionReferentiel, type FonctionReferentiel } from '@/features/personnel/api'
import { fetchSchools } from '@/features/classes/api'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { erreur, succes } from '@/shared/lib/alertes'

interface FonctionReferentielFormModalProps {
  fonction?: FonctionReferentiel | null
  onClose: () => void
  onSaved: () => void
}

interface FormValues {
  label_fr: string
  label_en: string
  school_id?: number
}

export function FonctionReferentielFormModal({ fonction, onClose, onSaved }: FonctionReferentielFormModalProps) {
  const { t } = useTranslation()
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })
  const {
    register,
    handleSubmit,
    reset,
    formState: { isSubmitting, errors },
  } = useForm<FormValues>({
    defaultValues: {
      label_fr: fonction?.label_fr ?? '',
      label_en: fonction?.label_en ?? '',
      school_id: fonction?.school_id,
    },
  })

  useEffect(() => {
    reset({
      label_fr: fonction?.label_fr ?? '',
      label_en: fonction?.label_en ?? '',
      school_id: fonction?.school_id,
    })
  }, [fonction, reset])

  const onSubmit = async (values: FormValues) => {
    try {
      if (fonction) {
        await updateFonctionReferentiel(fonction.id, {
          label_fr: values.label_fr.trim(),
          label_en: values.label_en.trim() || null,
        })
        succes(t('fonctionsReferentiel.updated'))
      } else {
        await createFonctionReferentiel({
          label_fr: values.label_fr.trim(),
          label_en: values.label_en.trim() || null,
          school_id: values.school_id ? Number(values.school_id) : undefined,
        })
        succes(t('fonctionsReferentiel.created'))
      }
      onSaved()
      onClose()
    } catch (err: any) {
      erreur(err.message || t('common.error_generic'))
    }
  }

  return (
    <Modal title={fonction ? t('fonctionsReferentiel.edit') : t('fonctionsReferentiel.create')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        {!fonction && (
          <Select
            label={`${t('classes.ecole')}${(schools?.length ?? 0) > 1 ? ' *' : ''}`}
            error={errors.school_id?.message}
            {...register('school_id', { required: (schools?.length ?? 0) > 1 ? "L'école est requise." : false })}
          >
            <option value="">—</option>
            {schools?.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </Select>
        )}
        <Input
          label={t('fonctionsReferentiel.label_fr')}
          placeholder={t('fonctionsReferentiel.label_fr_placeholder')}
          error={errors.label_fr?.message}
          {...register('label_fr', { required: t('fonctionsReferentiel.label_fr_required') })}
        />
        <Input label={t('fonctionsReferentiel.label_en')} placeholder={t('fonctionsReferentiel.label_en_placeholder')} {...register('label_en')} />

        <div className="flex justify-end gap-3 pt-4">
          <Button variant="secondary" onClick={onClose} type="button">
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? t('common.saving') : fonction ? t('common.update') : t('common.create')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
