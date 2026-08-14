import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { createFonctionReferentiel, updateFonctionReferentiel, type FonctionReferentiel } from '@/features/personnel/api'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { erreur, succes } from '@/shared/lib/alertes'

interface FonctionReferentielFormModalProps {
  fonction?: FonctionReferentiel | null
  onClose: () => void
  onSaved: () => void
}

interface FormValues {
  label_fr: string
  label_en: string
}

export function FonctionReferentielFormModal({ fonction, onClose, onSaved }: FonctionReferentielFormModalProps) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { isSubmitting, errors },
  } = useForm<FormValues>({
    defaultValues: {
      label_fr: fonction?.label_fr ?? '',
      label_en: fonction?.label_en ?? '',
    },
  })

  useEffect(() => {
    reset({
      label_fr: fonction?.label_fr ?? '',
      label_en: fonction?.label_en ?? '',
    })
  }, [fonction, reset])

  const onSubmit = async (values: FormValues) => {
    const payload = {
      label_fr: values.label_fr.trim(),
      label_en: values.label_en.trim() || null,
    }

    try {
      if (fonction) {
        await updateFonctionReferentiel(fonction.id, payload)
        succes('Fonction mise à jour.')
      } else {
        await createFonctionReferentiel(payload)
        succes('Fonction créée.')
      }
      onSaved()
      onClose()
    } catch (err: any) {
      erreur(err.message || 'Une erreur est survenue.')
    }
  }

  return (
    <Modal title={fonction ? 'Modifier une fonction' : 'Créer une fonction'} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input
          label="Libellé français"
          placeholder="ex: Enseignant"
          error={errors.label_fr?.message}
          {...register('label_fr', { required: 'Le libellé français est requis' })}
        />
        <Input label="Libellé anglais" placeholder="ex: Teacher" {...register('label_en')} />

        <div className="flex justify-end gap-3 pt-4">
          <Button variant="secondary" onClick={onClose} type="button">
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'En cours...' : fonction ? 'Mettre à jour' : 'Créer'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
