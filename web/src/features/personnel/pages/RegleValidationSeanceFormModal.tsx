import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import {
  createRegleValidationSeance,
  updateRegleValidationSeance,
  type RegleValidationSeance,
  type RegleValidationSeancePayload,
} from '@/features/personnel/api'
import { fetchSchools } from '@/features/classes/api'
import { fetchSousSystemes } from '@/features/classes/sous-systemes/api'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Select, Input } from '@/shared/ui/Field'
import { erreur, succes } from '@/shared/lib/alertes'

interface RegleValidationSeanceFormModalProps {
  regle?: RegleValidationSeance | null
  onClose: () => void
  onSaved: () => void
}

const METHODES: [RegleValidationSeancePayload['methode_validation'], string][] = [
  ['qr', 'QR code de la salle'],
  ['code', 'Code court de la salle'],
  ['libre', 'Libre (aucune preuve exigée)'],
]

const UNITES: [RegleValidationSeancePayload['delai_unite'], string][] = [
  ['minutes', 'Minutes'],
  ['jours', 'Jours'],
  ['semaines', 'Semaines'],
]

export function RegleValidationSeanceFormModal({ regle, onClose, onSaved }: RegleValidationSeanceFormModalProps) {
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })
  const { data: sousSystemes } = useQuery({ queryKey: ['sous-systemes'], queryFn: fetchSousSystemes })

  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { isSubmitting, errors },
  } = useForm<RegleValidationSeancePayload>({
    defaultValues: {
      school_id: regle?.school_id,
      sous_systeme_id: regle?.sous_systeme_id ?? null,
      methode_validation: regle?.methode_validation ?? 'qr',
      delai_valeur: regle?.delai_valeur ?? 15,
      delai_unite: regle?.delai_unite ?? 'minutes',
    },
  })

  useEffect(() => {
    reset({
      school_id: regle?.school_id,
      sous_systeme_id: regle?.sous_systeme_id ?? null,
      methode_validation: regle?.methode_validation ?? 'qr',
      delai_valeur: regle?.delai_valeur ?? 15,
      delai_unite: regle?.delai_unite ?? 'minutes',
    })
  }, [regle, reset])

  const ecoleChoisie = watch('school_id')
  const sousSystemesFiltres = ecoleChoisie
    ? sousSystemes?.filter((s) => s.school_id === Number(ecoleChoisie))
    : sousSystemes

  const onSubmit = async (values: RegleValidationSeancePayload) => {
    try {
      const payload: RegleValidationSeancePayload = {
        ...values,
        school_id: values.school_id ? Number(values.school_id) : undefined,
        sous_systeme_id: values.sous_systeme_id ? Number(values.sous_systeme_id) : null,
        delai_valeur: Number(values.delai_valeur),
      }

      if (regle) {
        await updateRegleValidationSeance(regle.id, payload)
        succes('Règle mise à jour.')
      } else {
        await createRegleValidationSeance(payload)
        succes('Règle créée.')
      }
      onSaved()
      onClose()
    } catch (err: any) {
      erreur(err.message || 'Une erreur est survenue.')
    }
  }

  return (
    <Modal title={regle ? 'Modifier la règle' : 'Nouvelle règle de validation'} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        {!regle && (
          <Select
            label={`École${(schools?.length ?? 0) > 1 ? ' *' : ''}`}
            error={errors.school_id?.message}
            {...register('school_id', { required: (schools?.length ?? 0) > 1 ? "L'école est requise." : false })}
          >
            <option value="">—</option>
            {schools?.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </Select>
        )}
        {!regle && (
          <Select label="Sous-système" {...register('sous_systeme_id')}>
            <option value="">Tous les sous-systèmes de l'école</option>
            {sousSystemesFiltres?.map((s) => (
              <option key={s.id} value={s.id}>
                {s.nom}
              </option>
            ))}
          </Select>
        )}
        <Select label="Mode de validation" {...register('methode_validation', { required: true })}>
          {METHODES.map(([valeur, libelle]) => (
            <option key={valeur} value={valeur}>
              {libelle}
            </option>
          ))}
        </Select>
        <div className="grid grid-cols-2 gap-3">
          <Input
            type="number"
            min={1}
            label="Délai avant blocage"
            error={errors.delai_valeur?.message}
            {...register('delai_valeur', { required: 'Le délai est requis.', min: { value: 1, message: 'Au moins 1.' } })}
          />
          <Select label="Unité" {...register('delai_unite', { required: true })}>
            {UNITES.map(([valeur, libelle]) => (
              <option key={valeur} value={valeur}>
                {libelle}
              </option>
            ))}
          </Select>
        </div>
        <p className="-mt-2 text-xs text-navy-400">
          Passé ce délai après la première déclaration de la séance, l'enseignant ne peut plus corriger l'appel ni les
          leçons cochées sans passer par le Surveillant Général.
        </p>

        <div className="flex justify-end gap-3 pt-4">
          <Button variant="secondary" onClick={onClose} type="button">
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Enregistrement…' : regle ? 'Mettre à jour' : 'Créer'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
