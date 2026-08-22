import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Mail, Lock, ShieldCheck, Eye, EyeOff } from 'lucide-react'
import logoWordmark from '@/assets/logo-wordmark.png'
import logoMark from '@/assets/logo-mark.png'
import { login, fetchMe } from '@/features/auth/api'
import { useAuthStore } from '@/shared/store/authStore'
import { useUiStore } from '@/shared/store/uiStore'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import type { ApiError } from '@/shared/types/api'

interface LoginForm {
  identifiant: string
  password: string
}

export function LoginPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const setSession = useAuthStore((s) => s.setSession)
  const { locale, setLocale } = useUiStore()
  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [showPassword, setShowPassword] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginForm>()

  const onSubmit = async (form: LoginForm) => {
    setServerError(null)
    setSubmitting(true)
    try {
      const { token } = await login(form)
      useAuthStore.setState({ token })
      const user = await fetchMe()
      setSession(token, user)
      // Un compte parent n'a pas `dashboard.view` : le tableau de bord du
      // personnel le renverrait dans une boucle de redirection.
      navigate(user.roles.includes('parent') ? '/parent' : '/', { replace: true })

      void window.desktop?.bootstrap({
        baseUrl: import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api/v1',
        token,
        schoolId: useAuthStore.getState().activeSchoolId,
        locale,
      }).catch(() => {
        // Le premier remplissage reprend au prochain appel réseau.
      })
    } catch (err) {
      setServerError((err as ApiError).message || t('auth.error_invalid'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-svh bg-cream-50">
      <div className="relative hidden flex-1 flex-col justify-between overflow-hidden bg-navy-800 p-12 text-cream-50 lg:flex">
        <div
          className="pointer-events-none absolute inset-0 opacity-[0.07]"
          style={{ backgroundImage: 'radial-gradient(circle at 1px 1px, white 1.5px, transparent 0)', backgroundSize: '28px 28px' }}
        />
        <div className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-gold-500/10 blur-3xl" aria-hidden />
        <div className="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-navy-500/30 blur-3xl" aria-hidden />

        <div className="relative flex items-center gap-2.5">
          <span className="flex h-14 items-center justify-center rounded-2xl bg-white px-3.5 py-2 shadow-lifted">
            <img src={logoWordmark} alt={t('app.name')} className="h-11 w-auto object-contain" />
          </span>
        </div>

        <div className="relative max-w-md">
          <h2 className="font-display text-4xl font-bold leading-tight tracking-tight">{t('auth.login_subtitle')}</h2>
          <div className="mt-6 flex items-center gap-2.5 text-sm text-navy-200">
            <ShieldCheck className="h-4 w-4 flex-none text-gold-300" />
            <span>{t('auth.secure_access')}</span>
          </div>
        </div>

        <p className="relative text-xs text-navy-300">© {new Date().getFullYear()} {t('app.name')}</p>
      </div>

      <div className="flex flex-1 items-center justify-center px-4 py-10">
        <div className="w-full max-w-sm">
          <div className="mb-8 flex items-center justify-between lg:hidden">
            <div className="flex items-center gap-2">
              <span className="flex h-11 items-center justify-center rounded-xl bg-navy-800 px-2.5 py-1.5 shadow-soft">
                <img src={logoMark} alt={t('app.name')} className="h-9 w-auto object-contain" />
              </span>
              <span className="font-display text-lg font-bold tracking-tight text-navy-800">{t('app.name')}</span>
            </div>
          </div>

          <div className="mb-6 flex items-center justify-end">
            <div className="flex gap-1 rounded-full bg-white p-1 text-xs font-bold shadow-soft">
              {(['fr', 'en'] as const).map((l) => (
                <button
                  key={l}
                  onClick={() => setLocale(l)}
                  className={`rounded-full px-2.5 py-1 transition-colors ${locale === l ? 'bg-green-500 text-white shadow-soft' : 'text-navy-400 hover:text-navy-600'
                    }`}
                >
                  {l.toUpperCase()}
                </button>
              ))}
            </div>
          </div>

          <div className="rounded-2xl border border-navy-100/70 bg-white p-8 shadow-lifted">
            <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('auth.login_title')}</h1>
            <p className="mb-7 mt-1.5 text-sm text-navy-400">{t('auth.login_subtitle')}</p>

            <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
              <Input
                label={t('auth.identifiant')}
                type="text"
                icon={Mail}
                autoComplete="username"
                error={errors.identifiant?.message}
                {...register('identifiant', { required: true })}
              />
              <div className="relative">
                <Input
                  label={t('auth.password')}
                  type={showPassword ? 'text' : 'password'}
                  icon={Lock}
                  autoComplete="current-password"
                  error={errors.password?.message}
                  className="pr-10"
                  {...register('password', { required: true })}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((value) => !value)}
                  aria-label={showPassword ? t('auth.hide_password') : t('auth.show_password')}
                  className="absolute right-3 top-[52%] -translate-y-1/2 text-navy-400 transition-colors hover:text-navy-600"
                >
                  {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>

              {serverError && (
                <p className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-600">{serverError}</p>
              )}

              <Button type="submit" disabled={submitting} className="mt-2 w-full">
                {t('auth.submit')}
              </Button>
            </form>
          </div>
        </div>
      </div>
    </div>
  )
}
