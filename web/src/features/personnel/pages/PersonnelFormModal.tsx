import { useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useState } from 'react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { fetchDepartements, createPersonnel, type PersonnelPayload } from '@/features/personnel/api'
import type { ApiError } from '@/shared/types/api'

export function PersonnelFormModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const { t } = useTranslation()
  const { data: departements } = useQuery({ queryKey: ['departements'], queryFn: fetchDepartements })
  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<PersonnelPayload>()

  const onSubmit = async (values: PersonnelPayload) => {
    setServerError(null)
    setSubmitting(true)
    try {
      await createPersonnel({
        ...values,
        departement_id: values.departement_id ? Number(values.departement_id) : null,
      })
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={t('personnel.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-3">
          <Input label={t('personnel.prenom')} error={errors.prenom?.message} {...register('prenom', { required: true })} />
          <Input label={t('personnel.nom')} error={errors.nom?.message} {...register('nom', { required: true })} />
        </div>
        <Input label={t('personnel.fonction')} error={errors.fonction?.message} {...register('fonction', { required: true })} />
        <Select label={t('personnel.departement')} {...register('departement_id')}>
          <option value="">—</option>
          {departements?.map((d) => (
            <option key={d.id} value={d.id}>
              {d.nom}
            </option>
          ))}
        </Select>
        <div className="grid grid-cols-2 gap-3">
          <Input label={t('personnel.matricule')} {...register('matricule')} />
          <Input label={t('personnel.telephone')} {...register('telephone')} />
        </div>
        <Input label={t('personnel.email')} type="email" {...register('email')} />

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
