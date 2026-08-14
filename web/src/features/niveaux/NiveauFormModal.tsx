import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { createNiveau, updateNiveau, type Niveau, type NiveauPayload } from './api'
import { fetchSousSystemes } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { erreur } from '@/shared/lib/alertes'

interface NiveauFormModalProps {
  isOpen: boolean
  niveau?: Niveau | null
  onClose: () => void
  onSubmit: (payload: NiveauPayload) => Promise<void>
  isLoading?: boolean
}

export function NiveauFormModal({ isOpen, niveau, onClose, onSubmit, isLoading = false }: NiveauFormModalProps) {
  const { t } = useTranslation()
  const ecoles = useAuthStore((s) => s.user?.ecoles_accessibles ?? [])
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const {
    register,
    handleSubmit,
    reset,
    formState: { isSubmitting },
  } = useForm<NiveauPayload>({
    defaultValues: {
      code: niveau?.code ?? '',
      name_fr: niveau?.name_fr ?? '',
      name_en: niveau?.name_en ?? '',
      school_id: niveau?.school_id ?? activeSchoolId ?? ecoles[0]?.id,
      sous_system_id: niveau?.sous_system_id ?? null,
    },
  })

  const { data: sousSystemes } = useQuery({
    queryKey: ['sous-systemes', activeSchoolId],
    queryFn: fetchSousSystemes,
  })

  useEffect(() => {
    reset({
      code: niveau?.code ?? '',
      name_fr: niveau?.name_fr ?? '',
      name_en: niveau?.name_en ?? '',
      school_id: niveau?.school_id ?? activeSchoolId ?? ecoles[0]?.id,
      sous_system_id: niveau?.sous_system_id ?? null,
    })
  }, [activeSchoolId, ecoles, niveau, reset])

  if (!isOpen) return null

  const submit = async (values: NiveauPayload) => {
    const payload = {
      ...values,
      school_id: Number(values.school_id),
      sous_system_id: values.sous_system_id ? Number(values.sous_system_id) : null,
    }

    try {
      if (niveau) {
        await updateNiveau(niveau.id, payload)
      } else {
        await createNiveau(payload)
      }
      await onSubmit(payload)
    } catch (err: any) {
      erreur(err.message || 'Une erreur est survenue.')
    }
  }

  return (
    <Modal title={niveau ? 'Modifier le niveau' : 'Créer un niveau'} onClose={onClose}>
      <form onSubmit={handleSubmit(submit)} className="space-y-4">
        <Select label="École" {...register('school_id', { required: true, valueAsNumber: true })}>
          <option value="">Sélectionner une école</option>
          {ecoles.map((ecole) => (
            <option key={ecole.id} value={ecole.id}>
              {ecole.name}
            </option>
          ))}
        </Select>

        <Input
          label={t('niveaux.code')}
          placeholder="ex: CE1, CLASS1"
          className="uppercase"
          {...register('code', {
            required: true,
            onChange: (e) => {
              e.target.value = e.target.value.toUpperCase()
            },
          })}
        />
        <Input label="Nom français" placeholder="Cours Élémentaire 1" {...register('name_fr', { required: true })} />
        <Input label="Nom anglais" placeholder="Elementary Course 1" {...register('name_en', { required: true })} />

        <Select label="Sous-système" {...register('sous_system_id', { valueAsNumber: true })}>
          <option value="">Aucun</option>
          {sousSystemes?.map((sousSysteme) => (
            <option key={sousSysteme.id} value={sousSysteme.id}>
              {sousSysteme.nom}
            </option>
          ))}
        </Select>

        <div className="flex justify-end gap-2 pt-4">
          <Button variant="secondary" onClick={onClose} disabled={isLoading || isSubmitting} type="button">
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isLoading || isSubmitting}>
            {niveau ? 'Mettre à jour' : 'Créer'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
