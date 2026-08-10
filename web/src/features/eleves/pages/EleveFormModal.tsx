import { useForm, useFieldArray } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { fetchClasses } from '@/features/classes/api'
import { createEleve, type ElevePayload } from '@/features/eleves/api'
import type { ApiError } from '@/shared/types/api'

export function EleveFormModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const { t } = useTranslation()
  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<ElevePayload>({ defaultValues: { tuteurs: [] } })

  const { fields, append, remove } = useFieldArray({ control, name: 'tuteurs' })

  const onSubmit = async (values: ElevePayload) => {
    setServerError(null)
    setSubmitting(true)
    try {
      await createEleve({ ...values, classe_id: values.classe_id ? Number(values.classe_id) : null })
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={t('eleves.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-3">
          <Input label={t('eleves.prenom')} error={errors.prenom?.message} {...register('prenom', { required: true })} />
          <Input label={t('eleves.nom')} error={errors.nom?.message} {...register('nom', { required: true })} />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <Select label={t('eleves.sexe')} error={errors.sexe?.message} {...register('sexe', { required: true })}>
            <option value="">—</option>
            <option value="F">{t('eleves.feminin')}</option>
            <option value="M">{t('eleves.masculin')}</option>
          </Select>
          <Input label={t('eleves.date_naissance')} type="date" {...register('date_naissance')} />
        </div>

        <Select label={t('eleves.classe')} {...register('classe_id')}>
          <option value="">—</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </Select>

        <div className="rounded-lg border border-dashed border-navy-200 p-3">
          <div className="mb-2 flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('eleves.tuteur')}</span>
            <button
              type="button"
              onClick={() => append({ nom: '', prenom: '', telephone: '', lien_parente: '', is_principal: fields.length === 0 })}
              className="flex items-center gap-1 text-xs font-semibold text-navy-600 hover:text-navy-800"
            >
              <Plus className="h-3.5 w-3.5" />
              {t('common.add')}
            </button>
          </div>

          {fields.map((field, index) => (
            <div key={field.id} className="mb-2 grid grid-cols-[1fr_1fr_1fr_auto] items-end gap-2 last:mb-0">
              <Input placeholder={t('eleves.tuteur_nom')} {...register(`tuteurs.${index}.prenom` as const, { required: true })} />
              <Input placeholder={t('eleves.nom')} {...register(`tuteurs.${index}.nom` as const, { required: true })} />
              <Input placeholder={t('eleves.tuteur_telephone')} {...register(`tuteurs.${index}.telephone` as const)} />
              <button
                type="button"
                onClick={() => remove(index)}
                className="rounded-lg p-2 text-navy-400 hover:bg-cream-100 hover:text-red-500"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ))}
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
