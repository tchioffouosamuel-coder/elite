import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { KeyRound, ShieldCheck } from 'lucide-react'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { succes } from '@/shared/lib/alertes'
import { useAuthStore } from '@/shared/store/authStore'
import { changerMotDePasse } from '@/features/auth/api'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  ancien_mot_de_passe: string
  nouveau_mot_de_passe: string
  nouveau_mot_de_passe_confirmation: string
}

/**
 * Renouvellement du mot de passe.
 *
 * Obligatoire à la première connexion d'un agent : son accès a été ouvert avec
 * le mot de passe commun à l'établissement, que tout le monde connaît. Tant
 * qu'il n'est pas remplacé, l'API refuse toute autre route (423) — cette page
 * est donc la seule sortie, et n'offre pas de bouton « plus tard ».
 */
export function ChangerMotDePassePage() {
  const navigate = useNavigate()
  const user = useAuthStore((s) => s.user)
  const refreshUser = useAuthStore((s) => s.refreshUser)
  const obligatoire = user?.doit_changer_mot_de_passe === true

  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    handleSubmit,
    watch,
    setError,
    formState: { errors },
  } = useForm<FormValues>()

  const onSubmit = async (values: FormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      refreshUser(await changerMotDePasse(values))
      succes('Mot de passe mis à jour.')
      navigate('/', { replace: true })
    } catch (err) {
      const e = err as ApiError
      const champs = e.errors ?? {}

      // Les erreurs de validation se posent sur le champ concerné ; le reste
      // s'affiche en tête de formulaire.
      const connus = ['ancien_mot_de_passe', 'nouveau_mot_de_passe'] as const
      const place = connus.filter((champ) => champs[champ]?.length)

      place.forEach((champ) => setError(champ, { message: champs[champ]![0] }))
      if (place.length === 0) setServerError(e.message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-svh items-center justify-center bg-cream-100 px-4 py-10">
      <div className="w-full max-w-md rounded-2xl border border-navy-100/70 bg-white p-6 shadow-lifted sm:p-8">
        <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-linear-to-br from-gold-50 to-gold-100 shadow-soft ring-1 ring-gold-100">
          <KeyRound className="h-5 w-5 text-gold-600" />
        </span>

        <h1 className="mt-4 font-display text-xl font-bold tracking-tight text-navy-900">
          {obligatoire ? 'Choisissez votre mot de passe' : 'Changer de mot de passe'}
        </h1>
        <p className="mt-1.5 text-sm text-navy-500">
          {obligatoire
            ? "Votre accès a été ouvert avec le mot de passe commun de l'établissement. Remplacez-le pour entrer dans l'application."
            : 'Vos autres sessions seront fermées.'}
        </p>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-6 flex flex-col gap-4">
          <Input
            label={obligatoire ? 'Mot de passe reçu' : 'Mot de passe actuel'}
            type="password"
            autoComplete="current-password"
            error={errors.ancien_mot_de_passe?.message}
            {...register('ancien_mot_de_passe', { required: 'Saisissez votre mot de passe actuel.' })}
          />
          <Input
            label="Nouveau mot de passe"
            type="password"
            autoComplete="new-password"
            error={errors.nouveau_mot_de_passe?.message}
            {...register('nouveau_mot_de_passe', {
              required: 'Choisissez un nouveau mot de passe.',
              minLength: { value: 8, message: 'Huit caractères au minimum.' },
              validate: {
                lettresEtChiffres: (v) =>
                  (/[a-zA-Z]/.test(v) && /\d/.test(v)) || 'Mélangez lettres et chiffres.',
                different: (v, champs) =>
                  v !== champs.ancien_mot_de_passe || 'Choisissez un mot de passe différent du précédent.',
              },
            })}
          />
          <Input
            label="Confirmez le nouveau mot de passe"
            type="password"
            autoComplete="new-password"
            error={errors.nouveau_mot_de_passe_confirmation?.message}
            {...register('nouveau_mot_de_passe_confirmation', {
              validate: (v) => v === watch('nouveau_mot_de_passe') || 'La confirmation ne correspond pas.',
            })}
          />

          {serverError && <p className="text-sm text-red-500">{serverError}</p>}

          <Button type="submit" disabled={submitting} className="mt-2 w-full">
            <ShieldCheck className="h-4 w-4" />
            {submitting ? 'Enregistrement…' : 'Enregistrer et continuer'}
          </Button>
        </form>
      </div>
    </div>
  )
}
