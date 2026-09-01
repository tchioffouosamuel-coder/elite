import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ShieldCheck, Mail, ArrowLeft } from 'lucide-react'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { succes } from '@/shared/lib/alertes'
import { reinitialiserMotDePasseAvecOtp } from '@/features/auth/api'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  email: string
  otp: string
  nouveau_mot_de_passe: string
  nouveau_mot_de_passe_confirmation: string
}

/**
 * Seconde étape du mot de passe oublié : code reçu par e-mail + nouveau mot
 * de passe. L'adresse arrive pré-remplie depuis MotDePasseOubliePage (state
 * de navigation) mais reste modifiable — un accès direct à cette page (lien
 * d'un e-mail, retour arrière) doit pouvoir la saisir.
 */
export function ReinitialiserMotDePassePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const location = useLocation()
  const emailInitial = (location.state as { email?: string } | null)?.email ?? ''

  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    handleSubmit,
    watch,
    setError,
    formState: { errors },
  } = useForm<FormValues>({ defaultValues: { email: emailInitial } })

  const onSubmit = async (values: FormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      await reinitialiserMotDePasseAvecOtp(values)
      succes(t('auth.password_updated'))
      navigate('/connexion', { replace: true })
    } catch (err) {
      const e = err as ApiError
      const champs = e.errors ?? {}

      const connus = ['otp', 'nouveau_mot_de_passe'] as const
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
          <ShieldCheck className="h-5 w-5 text-gold-600" />
        </span>

        <h1 className="mt-4 font-display text-xl font-bold tracking-tight text-navy-900">
          {t('auth.reset_password_title')}
        </h1>
        <p className="mt-1.5 text-sm text-navy-500">{t('auth.reset_password_subtitle')}</p>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-6 flex flex-col gap-4">
          <Input
            label={t('auth.label_email')}
            type="email"
            icon={Mail}
            autoComplete="username"
            error={errors.email?.message}
            {...register('email', { required: t('auth.error_email_requis') })}
          />
          <Input
            label={t('auth.label_otp')}
            type="text"
            inputMode="numeric"
            autoComplete="one-time-code"
            maxLength={6}
            error={errors.otp?.message}
            {...register('otp', { required: t('auth.error_otp_requis') })}
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
            {submitting ? t('common.saving') : t('auth.submit_reset_password')}
          </Button>
        </form>

        <Link
          to="/connexion"
          className="mt-6 flex items-center justify-center gap-1.5 text-sm font-semibold text-navy-500 hover:text-navy-700"
        >
          <ArrowLeft className="h-4 w-4" />
          {t('auth.back_to_login')}
        </Link>
      </div>
    </div>
  )
}
