import { useState } from 'react'
import { Link } from 'react-router-dom'
import type { ComponentType } from 'react'
import { useForm } from 'react-hook-form'
import { Building2, IdCard, KeyRound, Pencil, Settings, ShieldCheck, UserCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/shared/ui/Badge'
import { Card } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { useAuthStore, type AuthUser } from '@/shared/store/authStore'
import { changerMotDePasse, updateProfil } from '@/features/auth/api'
import { succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TYPE_TONE: Record<string, 'blue' | 'green' | 'gold'> = {
  maternelle: 'gold',
  primaire: 'green',
  secondaire: 'blue',
}

function initials(name?: string): string {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase()
}

export function UserProfilePage() {
  const { t } = useTranslation()
  const { user, activeSchool, can } = useAuthStore()
  const ecoleActive = activeSchool()

  if (!user) return null

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('profile.title')}
        sousTitre={t('profile.subtitle')}
        icon={UserCircle}
      />

      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)]">
        <Card className="flex flex-col gap-5">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
            <span className="flex h-20 w-20 flex-none items-center justify-center rounded-2xl bg-navy-800 font-display text-2xl font-bold text-cream-50 shadow-card">
              {initials(user.name)}
            </span>
            <div className="min-w-0">
              <h1 className="truncate font-display text-2xl font-bold tracking-tight text-navy-900">{user.name}</h1>
              <p className="mt-1 truncate text-sm text-navy-500">{user.email}</p>
              <div className="mt-3 flex flex-wrap gap-2">
                {user.is_super_admin && <Badge tone="gold">{t('profile.super_admin')}</Badge>}
                {user.est_enseignant && <Badge tone="blue">{t('profile.teacher')}</Badge>}
                {user.fonction && <Badge tone="neutral">{user.fonction}</Badge>}
              </div>
            </div>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <InfoTile icon={IdCard} label={t('profile.account_id')} value={`#${user.id}`} />
            <InfoTile icon={ShieldCheck} label={t('profile.roles')} value={user.roles.length ? user.roles.join(', ') : '—'} />
            <InfoTile icon={Building2} label={t('profile.active_school')} value={ecoleActive?.name ?? '—'} />
            <InfoTile icon={KeyRound} label={t('profile.permissions_count')} value={String(user.permissions.length)} />
          </div>
        </Card>

        <Card className="flex flex-col gap-4">
          <div>
            <h2 className="font-display text-base font-bold tracking-tight text-navy-900">{t('profile.quick_actions')}</h2>
            <p className="mt-1 text-sm text-navy-500">{t('profile.quick_actions_hint')}</p>
          </div>
          <div className="flex flex-col gap-2">
            {can('ecoles.manage') && (
              <Link
                to="/parametres"
                className="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-linear-to-b from-navy-700 to-navy-800 px-4 py-2.5 text-sm font-semibold tracking-tight text-cream-50 shadow-card transition-all duration-150 hover:from-navy-600 hover:to-navy-700 hover:shadow-lifted"
              >
                <Settings className="h-4 w-4" />
                {t('profile.open_settings')}
              </Link>
            )}
          </div>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <h2 className="mb-3 font-display text-base font-bold tracking-tight text-navy-900">{t('profile.accessible_schools')}</h2>
          <div className="flex flex-col gap-2">
            {user.ecoles_accessibles.map((ecole) => (
              <div
                key={ecole.id}
                className="flex items-center justify-between gap-3 rounded-xl border border-navy-100 bg-white/70 px-3 py-2.5"
              >
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-navy-800">{ecole.name}</p>
                  <p className="text-xs text-navy-400">{ecole.code}</p>
                </div>
                <Badge tone={TYPE_TONE[ecole.type] ?? 'neutral'}>{t(`profile.school_type.${ecole.type}`)}</Badge>
              </div>
            ))}
          </div>
        </Card>

        <Card>
          <h2 className="mb-3 font-display text-base font-bold tracking-tight text-navy-900">{t('profile.permissions')}</h2>
          <div className="flex max-h-80 flex-wrap gap-2 overflow-y-auto pr-1">
            {user.permissions.length === 0 ? (
              <p className="text-sm text-navy-400">{t('profile.no_permissions')}</p>
            ) : (
              user.permissions.map((permission) => (
                <span
                  key={permission}
                  className="rounded-full border border-navy-100 bg-white px-2.5 py-1 text-xs font-semibold text-navy-600 shadow-soft"
                >
                  {permission}
                </span>
              ))
            )}
          </div>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <ModifierProfilCard user={user} />
        <ChangerMotDePasseCard />
      </div>
    </div>
  )
}

interface ProfilFormValues {
  name: string
  email: string
  phone: string
}

function ModifierProfilCard({ user }: { user: AuthUser }) {
  const { t } = useTranslation()
  const refreshUser = useAuthStore((s) => s.refreshUser)
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ProfilFormValues>({
    defaultValues: { name: user.name, email: user.email, phone: user.phone ?? '' },
  })

  const onSubmit = async (values: ProfilFormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      refreshUser(await updateProfil({ ...values, phone: values.phone || null }))
      succes(t('profile.updated'))
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <Pencil className="h-4 w-4 text-navy-400" />
        <h2 className="font-display text-base font-bold tracking-tight text-navy-900">{t('profile.edit_title')}</h2>
      </div>
      <p className="-mt-2 text-sm text-navy-500">{t('profile.edit_hint')}</p>

      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('profile.name')}
          error={errors.name?.message}
          {...register('name', { required: true })}
        />
        <Input
          label={t('profile.email')}
          type="email"
          error={errors.email?.message}
          {...register('email', { required: true })}
        />
        <Input label={t('profile.phone')} placeholder={t('profile.phone_placeholder')} {...register('phone')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <Button type="submit" disabled={submitting} className="self-start">
          {submitting ? t('common.saving') : t('profile.save_changes')}
        </Button>
      </form>
    </Card>
  )
}

function ChangerMotDePasseCard() {
  const { t } = useTranslation()
  const refreshUser = useAuthStore((s) => s.refreshUser)
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    reset,
    setError,
    formState: { errors },
  } = useForm<{ ancien_mot_de_passe: string; nouveau_mot_de_passe: string; nouveau_mot_de_passe_confirmation: string }>()

  const onSubmit = handleSubmit(async (values) => {
    setServerError(null)
    setSubmitting(true)
    try {
      refreshUser(await changerMotDePasse(values))
      succes(t('auth.password_updated'))
      reset()
    } catch (err) {
      const e = err as ApiError
      const champs = e.errors ?? {}
      const connus = ['ancien_mot_de_passe', 'nouveau_mot_de_passe'] as const
      const place = connus.filter((champ) => champs[champ]?.length)

      place.forEach((champ) => setError(champ, { message: champs[champ]![0] }))
      if (place.length === 0) setServerError(e.message)
    } finally {
      setSubmitting(false)
    }
  })

  return (
    <Card className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <KeyRound className="h-4 w-4 text-navy-400" />
        <h2 className="font-display text-base font-bold tracking-tight text-navy-900">{t('profile.password_title')}</h2>
      </div>
      <p className="-mt-2 text-sm text-navy-500">{t('profile.password_hint')}</p>

      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input
          label={t('auth.label_mot_de_passe_actuel')}
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
              lettresEtChiffres: (v) => (/[a-zA-Z]/.test(v) && /\d/.test(v)) || t('auth.error_lettres_chiffres'),
              different: (v, champs) => v !== champs.ancien_mot_de_passe || t('auth.error_mot_de_passe_different'),
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

        <Button type="submit" disabled={submitting} className="self-start">
          {submitting ? t('common.saving') : t('profile.save_changes')}
        </Button>
      </form>
    </Card>
  )
}

function InfoTile({
  icon: Icon,
  label,
  value,
}: {
  icon: ComponentType<{ className?: string }>
  label: string
  value: string
}) {
  return (
    <div className="flex items-center gap-3 rounded-xl border border-navy-100 bg-white/70 px-3 py-3">
      <span className="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-cream-100 text-navy-600">
        <Icon className="h-4 w-4" />
      </span>
      <div className="min-w-0">
        <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{label}</p>
        <p className="truncate text-sm font-semibold text-navy-800">{value}</p>
      </div>
    </div>
  )
}
