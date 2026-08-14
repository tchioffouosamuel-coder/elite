import { useForm, useFieldArray } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { fetchClasses } from '@/features/classes/api'
import { createEleve, updateEleve, type Eleve, type ElevePayload } from '@/features/eleves/api'
import type { ApiError } from '@/shared/types/api'

export function EleveFormModal({
  eleve,
  onClose,
  onCreated,
}: {
  eleve?: Eleve
  onClose: () => void
  onCreated: () => void
}) {
  const { t } = useTranslation()
  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<ElevePayload>({
    defaultValues: eleve
      ? {
          nom_complet: eleve.nom_complet,
          sexe: eleve.sexe,
          date_naissance: eleve.date_naissance ?? '',
          classe_id: eleve.classe?.id,
          tuteurs: [],
        }
      : { tuteurs: [] },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'tuteurs' })

  const onSubmit = async (values: ElevePayload) => {
    setServerError(null)
    setSubmitting(true)
    try {
      const { tuteurs, ...rest } = values
      const payload: ElevePayload = {
        ...rest,
        classe_id: values.classe_id ? Number(values.classe_id) : null,
        // En édition, on ne touche aux tuteurs que si l'utilisateur en a saisi
        // ici : les envoyer vides écraserait les tuteurs déjà rattachés.
        ...(!eleve || (tuteurs && tuteurs.length > 0) ? { tuteurs: tuteurs ?? [] } : {}),
      }
      if (eleve) {
        await updateEleve(eleve.id, payload)
      } else {
        await createEleve(payload)
      }
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={eleve ? t('eleves.edit') : t('eleves.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('eleves.nom_complet')}
          error={errors.nom_complet?.message}
          {...register('nom_complet', { required: true })}
        />

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
              onClick={() => append({ nom_complet: '', telephone: '', lien_parente: '', is_principal: fields.length === 0 })}
              className="flex items-center gap-1 text-xs font-semibold text-navy-600 hover:text-navy-800"
            >
              <Plus className="h-3.5 w-3.5" />
              {t('common.add')}
            </button>
          </div>

          {eleve && eleve.tuteurs.length > 0 && fields.length === 0 && (
            <p className="mb-2 text-xs text-navy-400">
              {eleve.tuteurs.map((tut) => tut.nom_complet).join(', ')} — {t('common.add')} {t('eleves.tuteur').toLowerCase()} ci-dessous
              remplacera cette liste.
            </p>
          )}

          {fields.map((field, index) => (
            <div key={field.id} className="mb-2 grid grid-cols-[2fr_1fr_auto] items-end gap-2 last:mb-0">
              <Input placeholder={t('eleves.tuteur_nom')} {...register(`tuteurs.${index}.nom_complet` as const, { required: true })} />
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
