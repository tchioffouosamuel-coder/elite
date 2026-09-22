import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { createBanque, updateBanque, type Banque } from '@/features/personnel/api'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { erreur, succes } from '@/shared/lib/alertes'

interface BanqueFormModalProps {
  banque?: Banque | null
  onClose: () => void
  onSaved: () => void
}

interface FormValues {
  nom: string
  code: string
  numero_compte_ecole: string
}

export function BanqueFormModal({ banque, onClose, onSaved }: BanqueFormModalProps) {
  const { t } = useTranslation()
  const {
    register,
    handleSubmit,
    reset,
    formState: { isSubmitting, errors },
  } = useForm<FormValues>({
    defaultValues: {
      nom: banque?.nom ?? '',
      code: banque?.code ?? '',
      numero_compte_ecole: banque?.numero_compte_ecole ?? '',
    },
  })

  useEffect(() => {
    reset({
      nom: banque?.nom ?? '',
      code: banque?.code ?? '',
      numero_compte_ecole: banque?.numero_compte_ecole ?? '',
    })
  }, [banque, reset])

  const onSubmit = async (values: FormValues) => {
    try {
      if (banque) {
        await updateBanque(banque.id, {
          nom: values.nom.trim(),
          code: values.code.trim() || null,
          numero_compte_ecole: values.numero_compte_ecole.trim() || null,
        })
        succes(t('banques.updated'))
      } else {
        await createBanque({
          nom: values.nom.trim(),
          code: values.code.trim() || null,
          numero_compte_ecole: values.numero_compte_ecole.trim() || null,
        })
        succes(t('banques.created'))
      }
      onSaved()
      onClose()
    } catch (err: any) {
      erreur(err.message || t('common.error_generic'))
    }
  }

  return (
    <Modal title={banque ? t('banques.edit') : t('banques.create')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input
          label={t('banques.nom')}
          placeholder={t('banques.nom_placeholder')}
          error={errors.nom?.message}
          {...register('nom', { required: t('banques.nom_required') })}
        />
        <Input label={t('banques.code')} placeholder={t('banques.code_placeholder')} {...register('code')} />
        <Input
          label={t('banques.numero_compte_ecole')}
          placeholder={t('banques.numero_compte_ecole_placeholder')}
          {...register('numero_compte_ecole')}
        />

        <div className="flex justify-end gap-3 pt-4">
          <Button variant="secondary" onClick={onClose} type="button">
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? t('common.saving') : banque ? t('common.update') : t('common.create')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
