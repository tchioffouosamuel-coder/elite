import { useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useState } from 'react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { fetchNiveaux, fetchAnneesScolaires, createClasse, type ClassePayload } from '@/features/classes/api'
import { fetchEcole } from '@/features/settings/api'
import type { ApiError } from '@/shared/types/api'

export function ClasseFormModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const { t } = useTranslation()
  const { data: niveaux } = useQuery({ queryKey: ['niveaux'], queryFn: fetchNiveaux })
  const { data: ecole } = useQuery({ queryKey: ['ecole'], queryFn: fetchEcole })
  const { data: annees } = useQuery({ queryKey: ['annees-scolaires'], queryFn: fetchAnneesScolaires })
  const niveauxEcole = ecole ? niveaux?.filter((n) => ecole.niveau_ids.includes(n.id)) : niveaux
  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const activeAnnee = annees?.find((a) => a.is_active) ?? annees?.[0]

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ClassePayload>({ defaultValues: { annee_scolaire_id: activeAnnee?.id } })

  const onSubmit = async (values: ClassePayload) => {
    setServerError(null)
    setSubmitting(true)
    try {
      await createClasse({
        ...values,
        niveau_id: Number(values.niveau_id),
        annee_scolaire_id: Number(values.annee_scolaire_id),
        capacite: values.capacite ? Number(values.capacite) : null,
      })
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={t('classes.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input label={t('classes.nom')} error={errors.nom?.message} {...register('nom', { required: true })} />

        <Select label={t('classes.niveau')} error={errors.niveau_id?.message} {...register('niveau_id', { required: true })}>
          <option value="">—</option>
          {niveauxEcole?.map((n) => (
            <option key={n.id} value={n.id}>
              {n.name_fr}
            </option>
          ))}
        </Select>

        <Select
          label="Année scolaire"
          error={errors.annee_scolaire_id?.message}
          {...register('annee_scolaire_id', { required: true })}
        >
          {annees?.map((a) => (
            <option key={a.id} value={a.id}>
              {a.libelle}
            </option>
          ))}
        </Select>

        <div className="grid grid-cols-2 gap-3">
          <Input label={t('classes.filiere')} {...register('filiere')} />
          <Input label={t('classes.capacite')} type="number" {...register('capacite')} />
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
