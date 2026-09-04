import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { KeyRound, ShieldCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { succes } from '@/shared/lib/alertes'
import { useAuthStore } from '@/shared/store/authStore'
import { changerMotDePasse, logout } from '@/features/auth/api'
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
  const { t } = useTranslation()
  const navigate = useNavigate()
  const user = useAuthStore((s) => s.user)
  const refreshUser = useAuthStore((s) => s.refreshUser)
  const clearSession = useAuthStore((s) => s.clearSession)
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

  const retourConnexion = async () => {
    try {
      await logout()
    } finally {
      clearSession()
      navigate('/connexion', { replace: true })
    }
  }

  const onSubmit = async (values: FormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      refreshUser(await changerMotDePasse(values))
      succes(t('auth.password_updated'))
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
          {obligatoire ? t('auth.change_password_title_obligatoire') : t('auth.change_password_title')}
        </h1>
        <p className="mt-1.5 text-sm text-navy-500">
          {obligatoire ? t('auth.change_password_subtitle_obligatoire') : t('auth.change_password_subtitle')}
        </p>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-6 flex flex-col gap-4">
          <Input
            label={obligatoire ? t('auth.label_mot_de_passe_recu') : t('auth.label_mot_de_passe_actuel')}
            type="password"
            autoComplete="current-password"
            error={errors.ancien_mot_de_passe?.message}
            {...register('ancien_mot_de_passe', { required: t('auth.error_mot_de_passe_actuel_requis') })}
          />
          <Input
            label={t('auth.label_nouveau_mot_de_passe')}
            type="password"
            autoComplete="new-password"
            error={errors.nouveau_mot_de_passe?.message}
            {...register('nouveau_mot_de_passe', {
              required: t('auth.error_nouveau_mot_de_passe_requis'),
              minLength: { value: 8, message: t('auth.error_min_length') },
              validate: {
                lettresEtChiffres: (v) =>
                  (/[a-zA-Z]/.test(v) && /\d/.test(v)) || t('auth.error_lettres_chiffres'),
                different: (v, champs) =>
                  v !== champs.ancien_mot_de_passe || t('auth.error_mot_de_passe_different'),
              },
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

          {serverError && <p className="text-sm text-red-500">{serverError}</p>}

          <Button type="submit" disabled={submitting} className="mt-2 w-full">
            <ShieldCheck className="h-4 w-4" />
            {submitting ? t('common.saving') : t('auth.submit_save_continue')}
          </Button>

          <button
            type="button"
            onClick={retourConnexion}
            className="text-center text-sm font-medium text-navy-500 hover:text-navy-700"
          >
            {t('auth.back_to_login')}
          </button>
        </form>
      </div>
    </div>
  )
}
