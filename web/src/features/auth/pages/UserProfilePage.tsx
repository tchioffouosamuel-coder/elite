import { Link } from 'react-router-dom'
import type { ComponentType } from 'react'
import { Building2, IdCard, KeyRound, Settings, ShieldCheck, UserCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/shared/ui/Badge'
import { Card } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { useAuthStore } from '@/shared/store/authStore'

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
    </div>
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
