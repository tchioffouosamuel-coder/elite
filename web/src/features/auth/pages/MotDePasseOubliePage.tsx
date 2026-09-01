import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { KeyRound, Mail, ArrowLeft } from 'lucide-react'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { demanderOtpMotDePasse } from '@/features/auth/api'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  email: string
}

/**
 * Première étape du mot de passe oublié : demande d'un code par e-mail.
 * Réservée au personnel — un compte parent se connecte par téléphone (cf.
 * LoginPage) et n'a pas d'adresse à qui envoyer un code ; il doit passer par
 * un administrateur pour la réinitialisation.
 *
 * La réponse est toujours un succès côté API, même si l'adresse ne
 * correspond à aucun compte (pas d'énumération) : on avance donc
 * systématiquement vers l'étape suivante après soumission.
 */
export function MotDePasseOubliePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>()

  const onSubmit = async (form: FormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      await demanderOtpMotDePasse(form.email)
      navigate('/reinitialiser-mot-de-passe', { state: { email: form.email }, replace: true })
    } catch (err) {
      setServerError((err as ApiError).message)
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
          {t('auth.forgot_password_title')}
        </h1>
        <p className="mt-1.5 text-sm text-navy-500">{t('auth.forgot_password_subtitle')}</p>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-6 flex flex-col gap-4">
          <Input
            label={t('auth.label_email')}
            type="email"
            icon={Mail}
            autoComplete="username"
            error={errors.email?.message}
            {...register('email', { required: t('auth.error_email_requis') })}
          />

          {serverError && <p className="text-sm text-red-500">{serverError}</p>}

          <Button type="submit" disabled={submitting} className="mt-2 w-full">
            {submitting ? t('common.saving') : t('auth.submit_send_otp')}
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
