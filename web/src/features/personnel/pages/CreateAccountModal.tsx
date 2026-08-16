import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useState } from 'react'
import { Modal } from '@/shared/ui/Modal'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { createLoginAccount } from '@/features/personnel/api'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  email: string
}

/**
 * Ouverture manuelle d'un accès.
 *
 * Depuis que le compte est ouvert à la création de la fiche, cet écran ne sert
 * plus qu'aux agents enregistrés avant ce changement, ou pour imposer une
 * adresse précise. Le choix du rôle a disparu : un compte d'agent tient ses
 * droits de sa fonction, et le laisser cocher rendait deux agents de même
 * fonction inégaux sans que rien ne l'explique.
 */
export function CreateAccountModal({
  personnelId,
  onClose,
  onCreated,
}: {
  personnelId: number
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
  } = useForm<FormValues>({ defaultValues: { email: '' } })

  const onSubmit = async (values: FormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      await createLoginAccount(personnelId, values.email || undefined)
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={t('personnel.create_account')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={`${t('personnel.email')} (facultatif)`}
          type="email"
          placeholder="laissez vide pour une adresse dérivée du nom"
          error={errors.email?.message}
          {...register('email')}
        />
        <p className="rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
          Sans adresse saisie, l'accès est ouvert sous la forme{' '}
          <code className="font-semibold">prenom.nom@elite.school</code>, avec le mot de passe par défaut de
          l'établissement. L'agent reçoit les privilèges de sa fonction, et uniquement ceux-là.
        </p>

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
