import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useState } from 'react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { createLoginAccount } from '@/features/personnel/api'
import type { ApiError } from '@/shared/types/api'

const ASSIGNABLE_ROLES = ['enseignant', 'censeur_sg', 'econome', 'admin_etablissement']

interface FormValues {
  email: string
  role: string
}

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
  } = useForm<FormValues>({ defaultValues: { role: 'enseignant' } })

  const onSubmit = async (values: FormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      await createLoginAccount(personnelId, values.email, values.role)
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
          label={t('personnel.email')}
          type="email"
          error={errors.email?.message}
          {...register('email', { required: true })}
        />
        <Select label="Rôle" {...register('role', { required: true })}>
          {ASSIGNABLE_ROLES.map((r) => (
            <option key={r} value={r}>
              {r}
            </option>
          ))}
        </Select>
        <p className="text-xs text-navy-400">Un mot de passe temporaire sera généré et à transmettre à l'intéressé.</p>

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
