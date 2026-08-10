import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  GraduationCap,
  LayoutDashboard,
  Users,
  School,
  UserRound,
  LogOut,
  Building2,
  BookOpen,
  ShieldAlert,
  Trophy,
  Settings,
  CalendarRange,
  CalendarClock,
  ClipboardCheck,
  FileText,
  ListChecks,
  IdCard,
  Layers,
} from 'lucide-react'
import { clsx } from 'clsx'
import { useAuthStore } from '@/shared/store/authStore'
import { useUiStore } from '@/shared/store/uiStore'
import { logout } from '@/features/auth/api'
import { SchoolSwitcher } from './SchoolSwitcher'

type TypeEcole = 'maternelle' | 'primaire' | 'secondaire'

/**
 * `types` restreint une entrée aux établissements concernés : le secondaire
 * s'organise en départements, le primaire et la maternelle en niveaux
 * d'enseignement. Sans `types`, l'entrée vaut pour toutes les écoles.
 */
const navGroups = [
  {
    label: 'nav.group.overview',
    items: [{ to: '/', label: 'nav.dashboard', icon: LayoutDashboard, permission: 'dashboard.view' }],
  },
  {
    label: 'nav.group.staff',
    items: [
      { to: '/personnel', label: 'nav.personnel', icon: Users, permission: 'personnel.view' },
      {
        to: '/departements',
        label: 'nav.departements',
        icon: Building2,
        permission: 'personnel.view',
        types: ['secondaire'] as TypeEcole[],
      },
      {
        to: '/niveaux',
        label: 'nav.niveaux',
        icon: Layers,
        permission: 'pedagogie.view',
        types: ['primaire', 'maternelle'] as TypeEcole[],
      },
    ],
  },
  {
    label: 'nav.group.classes',
    items: [
      { to: '/classes', label: 'nav.classes', icon: School, permission: 'classes.view' },
      { to: '/eleves', label: 'nav.eleves', icon: UserRound, permission: 'eleves.view' },
    ],
  },
  {
    label: 'nav.group.pedagogie',
    items: [
      { to: '/matieres', label: 'nav.matieres', icon: BookOpen, permission: 'pedagogie.view' },
      { to: '/emploi-du-temps', label: 'nav.emploiDuTemps', icon: CalendarClock, permission: 'emploi_du_temps.view' },
      { to: '/seances', label: 'nav.seances', icon: ClipboardCheck, permission: 'emploi_du_temps.view' },
    ],
  },
  {
    label: 'nav.group.discipline',
    items: [{ to: '/sanctions', label: 'nav.sanctions', icon: ShieldAlert, permission: 'discipline.view' }],
  },
  {
    label: 'nav.group.resultats',
    items: [
      { to: '/bulletins', label: 'nav.bulletins', icon: FileText, permission: 'bulletins.view' },
      { to: '/remplissage', label: 'nav.remplissage', icon: ListChecks, permission: 'notes.view' },
      { to: '/palmares', label: 'nav.palmares', icon: Trophy, permission: 'bulletins.view' },
    ],
  },
  {
    label: 'nav.group.identification',
    items: [{ to: '/identification', label: 'nav.identification', icon: IdCard, permission: 'eleves.view' }],
  },
  {
    label: 'nav.group.admin',
    items: [
      { to: '/session', label: 'nav.session', icon: CalendarRange, permission: 'ecoles.manage' },
      { to: '/parametres', label: 'nav.parametres', icon: Settings, permission: 'ecoles.manage' },
    ],
  },
] as const

function initials(name?: string) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase()
}

export function AppLayout() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { user, can, clearSession, activeSchool } = useAuthStore()
  const { locale, setLocale } = useUiStore()
  const typeEcole = activeSchool()?.type

  const handleLogout = async () => {
    try {
      await logout()
    } finally {
      clearSession()
      navigate('/connexion', { replace: true })
    }
  }

  return (
    <div className="flex h-svh overflow-hidden bg-cream-50">
      <aside className="flex w-64 flex-none flex-col overflow-y-auto bg-navy-800 text-cream-50">
        <div className="flex items-center gap-2.5 px-6 py-6">
          <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gold-500/15 ring-1 ring-gold-500/30">
            <GraduationCap className="h-5 w-5 text-gold-300" />
          </span>
          <span className="font-display text-lg font-bold tracking-tight">{t('app.name')}</span>
        </div>

        <nav className="flex flex-1 flex-col gap-4 px-3 pt-2 pb-4">
          {navGroups
            .map((group) => ({
              ...group,
              items: group.items.filter(
                (item) =>
                  can(item.permission) &&
                  (!('types' in item) || !typeEcole || (item.types as TypeEcole[]).includes(typeEcole)),
              ),
            }))
            .filter((group) => group.items.length > 0)
            .map((group) => (
              <div key={group.label} className="flex flex-col gap-1">
                <span className="px-3.5 pb-1 text-[10px] font-bold uppercase tracking-wider text-navy-400">
                  {t(group.label)}
                </span>
                {group.items.map((item) => (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    end={item.to === '/'}
                    className={({ isActive }) =>
                      clsx(
                        'group relative flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-colors',
                        isActive ? 'bg-white/10 text-white' : 'text-navy-200 hover:bg-white/5 hover:text-white',
                      )
                    }
                  >
                    {({ isActive }) => (
                      <>
                        {isActive && (
                          <span className="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-gold-400" />
                        )}
                        <item.icon className={clsx('h-[18px] w-[18px]', isActive ? 'text-gold-300' : 'text-navy-300 group-hover:text-gold-200')} />
                        {t(item.label)}
                      </>
                    )}
                  </NavLink>
                ))}
              </div>
            ))}
        </nav>

        <div className="flex items-center gap-3 border-t border-white/10 px-4 py-4">
          <span className="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-gold-500/20 text-xs font-bold text-gold-200 ring-1 ring-gold-400/30">
            {initials(user?.name)}
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-semibold text-white">{user?.name}</p>
            <p className="truncate text-xs text-navy-300">{user?.roles.join(', ')}</p>
          </div>
          <button
            onClick={handleLogout}
            title={t('nav.logout')}
            className="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-navy-300 transition-colors hover:bg-white/10 hover:text-white"
          >
            <LogOut className="h-4 w-4" />
          </button>
        </div>
      </aside>

      <div className="flex min-h-0 flex-1 flex-col">
        <header className="flex flex-none items-center justify-between border-b border-navy-100 bg-white/80 px-6 py-3.5 backdrop-blur-sm">
          <div className="flex items-center gap-3">
            <SchoolSwitcher />
            <div className="flex gap-1 rounded-full bg-cream-100 p-1 text-xs font-bold">
            {(['fr', 'en'] as const).map((l) => (
              <button
                key={l}
                onClick={() => setLocale(l)}
                className={clsx(
                  'rounded-full px-2.5 py-1 transition-colors',
                  locale === l ? 'bg-gold-500 text-navy-900 shadow-soft' : 'text-navy-400 hover:text-navy-600',
                )}
              >
                {l.toUpperCase()}
              </button>
            ))}
            </div>
          </div>

          <div className="flex items-center gap-3">
            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-navy-700 text-xs font-bold text-cream-50">
              {initials(user?.name)}
            </span>
            <div className="text-right text-sm leading-tight">
              <p className="font-semibold text-navy-800">{user?.name}</p>
              <p className="text-xs text-navy-400">{user?.roles.join(', ')}</p>
            </div>
          </div>
        </header>

        <main className="min-h-0 flex-1 overflow-y-auto p-6 lg:p-8">
          <div className="mx-auto max-w-7xl">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}
