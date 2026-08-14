import { useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useState } from 'react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import {
  fetchDepartements,
  fetchFonctionsReferentiel,
  createPersonnel,
  updatePersonnel,
  type Personnel,
  type PersonnelPayload,
} from '@/features/personnel/api'
import { estSecondaire } from '@/shared/lib/ecole'
import { useAuthStore } from '@/shared/store/authStore'
import type { ApiError } from '@/shared/types/api'

export function PersonnelFormModal({
  personnel,
  onClose,
  onCreated,
}: {
  personnel?: Personnel
  onClose: () => void
  onCreated: () => void
}) {
  const { t } = useTranslation()
  const avecDepartements = estSecondaire()
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const { data: departements } = useQuery({
    queryKey: ['departements', activeSchoolId],
    queryFn: fetchDepartements,
    enabled: avecDepartements,
  })
  const { data: fonctions } = useQuery({
    queryKey: ['fonctions-referentiel', activeSchoolId],
    queryFn: fetchFonctionsReferentiel,
  })
  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<PersonnelPayload>({
    defaultValues: personnel
      ? {
          nom_complet: personnel.nom_complet,
          fonction_id: personnel.fonction_id,
          departement_id: personnel.departement?.id ?? undefined,
          matricule: personnel.matricule ?? '',
          telephone: personnel.telephone ?? '',
          email: personnel.email ?? '',
        }
      : undefined,
  })

  const onSubmit = async (values: PersonnelPayload) => {
    setServerError(null)
    setSubmitting(true)
    try {
      const payload = {
        ...values,
        fonction_id: Number(values.fonction_id),
        departement_id: values.departement_id ? Number(values.departement_id) : null,
      }
      if (personnel) {
        await updatePersonnel(personnel.id, payload)
      } else {
        await createPersonnel(payload)
      }
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={personnel ? t('personnel.edit') : t('personnel.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('personnel.nom_complet')}
          error={errors.nom_complet?.message}
          {...register('nom_complet', { required: true })}
        />
        <Select label={t('personnel.fonction')} error={errors.fonction_id?.message} {...register('fonction_id', { required: true })}>
          <option value="">—</option>
          {fonctions?.map((fonction) => (
            <option key={fonction.id} value={fonction.id}>
              {fonction.label}
            </option>
          ))}
        </Select>
        {avecDepartements && (
          <Select label={t('personnel.departement')} {...register('departement_id')}>
            <option value="">—</option>
            {departements?.map((d) => (
              <option key={d.id} value={d.id}>
                {d.nom}
              </option>
            ))}
          </Select>
        )}
        <div className="grid grid-cols-2 gap-3">
          <Input label={t('personnel.matricule')} {...register('matricule')} />
          <Input label={t('personnel.telephone')} {...register('telephone')} />
        </div>
        <Input
          label={t('personnel.email')}
          type="email"
          disabled={!!personnel}
          {...register('email', { disabled: !!personnel })}
        />

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
