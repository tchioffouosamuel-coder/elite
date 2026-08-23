import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { KeyRound } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { succes, erreur } from '@/shared/lib/alertes'
import { reinitialiserMotDePasseCompte, type CompteUtilisateur } from '@/features/comptes/api'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  nouveau_mot_de_passe: string
  nouveau_mot_de_passe_confirmation: string
}

/**
 * Réinitialisation forcée par le super administrateur — sans l'ancien mot de
 * passe, qu'il ne connaît jamais (haché en base, cf. `CompteAgentService`).
 * Le compte devra le changer dès sa prochaine connexion, comme à l'ouverture
 * d'un accès neuf.
 */
export function ReinitialiserMotDePasseModal({
  compte,
  onClose,
  onReinitialise,
}: {
  compte: CompteUtilisateur
  onClose: () => void
  onReinitialise: () => void
}) {
  const { t } = useTranslation()
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    handleSubmit,
    watch,
    setError,
    formState: { errors },
  } = useForm<FormValues>()

  const onSubmit = async (values: FormValues) => {
    setSubmitting(true)
    try {
      await reinitialiserMotDePasseCompte(
        compte.id,
        values.nouveau_mot_de_passe,
        values.nouveau_mot_de_passe_confirmation,
      )
      succes(`Mot de passe réinitialisé pour ${compte.nom}.`)
      onReinitialise()
    } catch (err) {
      const e = err as ApiError
      const champs = e.errors ?? {}
      if (champs.nouveau_mot_de_passe?.length) {
        setError('nouveau_mot_de_passe', { message: champs.nouveau_mot_de_passe[0] })
      } else {
        erreur(e.message)
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={`Réinitialiser le mot de passe — ${compte.nom}`} onClose={onClose}>
      <p className="mb-4 flex items-start gap-2 rounded-xl bg-gold-50 px-3.5 py-2.5 text-sm text-gold-800 ring-1 ring-gold-100">
        <KeyRound className="mt-0.5 h-4 w-4 flex-none" />
        <span>
          Le compte devra changer ce mot de passe dès sa prochaine connexion, et sa session en cours (s'il y en a une)
          sera fermée.
        </span>
      </p>

      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('auth.label_nouveau_mot_de_passe')}
          type="password"
          autoComplete="new-password"
          autoFocus
          error={errors.nouveau_mot_de_passe?.message}
          {...register('nouveau_mot_de_passe', {
            required: t('auth.error_nouveau_mot_de_passe_requis'),
            minLength: { value: 8, message: t('auth.error_min_length') },
            validate: (v) => (/[a-zA-Z]/.test(v) && /\d/.test(v)) || t('auth.error_lettres_chiffres'),
          })}
        />
        <Input
          label={t('auth.label_confirmer_mot_de_passe')}
          type="password"
          autoComplete="new-password"
          error={errors.nouveau_mot_de_passe_confirmation?.message}
          {...register('nouveau_mot_de_passe_confirmation', {
            validate: (v) => v === watch('nouveau_mot_de_passe') || t('auth.error_confirmation_mismatch'),
          })}
        />

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={submitting}>
            <KeyRound className="h-4 w-4" />
            {submitting ? t('common.saving') : 'Réinitialiser'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
