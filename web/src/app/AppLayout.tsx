import { useEffect, useMemo, useState } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
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
  Menu,
  X,
  BarChart3,
  ScanFace,
  Layers,
  GitBranch,
  CalendarCheck,
  BriefcaseBusiness,
  ChevronDown,
  Search,
  Repeat,
} from 'lucide-react'
import { clsx } from 'clsx'
import { useAuthStore } from '@/shared/store/authStore'
import { useUiStore } from '@/shared/store/uiStore'
import { logout } from '@/features/auth/api'
import { SchoolSwitcher } from './SchoolSwitcher'

type TypeEcole = 'maternelle' | 'primaire' | 'secondaire'

/**
 * `types` restreint une entrée aux établissements concernés : le secondaire
 * s'organise en départements, le primaire en niveaux d'enseignement, et la
 * maternelle en simples sections. Sans `types`, l'entrée vaut pour toutes.
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
      { to: '/fonctions-referentiel', label: 'nav.fonctionsReferentiel', icon: BriefcaseBusiness, permission: 'personnel.manage', superAdminOnly: true },
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
        // La maternelle ne s'organise pas en degrés : l'entrée ne la concerne pas.
        types: ['primaire'] as TypeEcole[],
      },
    ],
  },
  {
    label: 'nav.group.classes',
    items: [
      { to: '/classes', label: 'nav.classes', icon: School, permission: 'classes.view' },
      { to: '/sous-systemes', label: 'nav.sousSystemes', icon: Layers, permission: 'classes.manage' },
      { to: '/eleves', label: 'nav.eleves', icon: UserRound, permission: 'eleves.view' },
      { to: '/eleves/transferts', label: 'nav.transferts', icon: Repeat, permission: 'eleves.manage' },
    ],
  },
  {
    label: 'nav.group.pedagogie',
    items: [
      { to: '/matieres', label: 'nav.matieres', icon: BookOpen, permission: 'pedagogie.view' },
      { to: '/progression', label: 'nav.progression', icon: GitBranch, permission: 'pedagogie.view' },
      {
        to: '/ma-journee',
        label: 'nav.maJournee',
        icon: CalendarCheck,
        permission: 'appel.manage',
        // Un tableau de bord personnel au titulaire/enseignant — pas un outil
        // de suivi pour l'administration, qui a ses propres écrans.
        roles: ['enseignant'] as string[],
      },
      { to: '/emploi-du-temps', label: 'nav.emploiDuTemps', icon: CalendarClock, permission: 'emploi_du_temps.view' },
      { to: '/seances', label: 'nav.seances', icon: ClipboardCheck, permission: 'emploi_du_temps.view' },
    ],
  },
  {
    label: 'nav.group.discipline',
    items: [
      {
        to: '/sanctions',
        label: 'nav.sanctions',
        icon: ShieldAlert,
        permission: 'discipline.view',
        // Corvée, exclusion temporaire ou définitive : des mesures qu'on ne
        // prononce pas contre de jeunes enfants. Le primaire et la maternelle
        // suivent l'assiduité par l'appel, sans sanctionner.
        types: ['secondaire'] as TypeEcole[],
      },
    ],
  },
  {
    label: 'nav.group.resultats',
    items: [
      { to: '/bulletins', label: 'nav.bulletins', icon: FileText, permission: 'bulletins.view' },
      { to: '/remplissage', label: 'nav.remplissage', icon: ListChecks, permission: 'notes.view' },
      { to: '/palmares', label: 'nav.palmares', icon: Trophy, permission: 'bulletins.view' },
      { to: '/stats-pedagogiques', label: 'nav.statsPedagogiques', icon: BarChart3, permission: 'bulletins.view' },
      { to: '/stats-disciplinaires', label: 'nav.statsDisciplinaires', icon: ShieldAlert, permission: 'discipline.view' },
    ],
  },
  {
    label: 'nav.group.identification',
    items: [
      { to: '/identification', label: 'nav.identification', icon: IdCard, permission: 'eleves.view' },
      { to: '/photos-examen', label: 'nav.photosExamen', icon: ScanFace, permission: 'eleves.view' },
    ],
  },
  {
    label: 'nav.group.admin',
    items: [
      { to: '/niveaux-globaux', label: 'nav.niveauxGlobaux', icon: Layers, permission: 'niveaux.view' },
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

function normaliserRecherche(texte: string): string {
  return texte
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase()
}

export function AppLayout() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const location = useLocation()
  const { user, can, clearSession, activeSchool } = useAuthStore()
  const { locale, setLocale } = useUiStore()
  const [menuOuvert, setMenuOuvert] = useState(false)
  const [rechercheMenu, setRechercheMenu] = useState('')
  const [groupesOuverts, setGroupesOuverts] = useState<Record<string, boolean>>(() =>
    Object.fromEntries(navGroups.map((group) => [group.label, true])),
  )
  const typeEcole = activeSchool()?.type

  // Le tiroir se referme à chaque navigation : sur mobile il recouvre la page,
  // le laisser ouvert masquerait l'écran qu'on vient d'ouvrir.
  useEffect(() => setMenuOuvert(false), [location.pathname])

  useEffect(() => {
    const groupeActif = navGroups.find((group) =>
      group.items.some((item) => (item.to === '/' ? location.pathname === '/' : location.pathname.startsWith(item.to))),
    )

    if (groupeActif) {
      setGroupesOuverts((actuel) => ({ ...actuel, [groupeActif.label]: true }))
    }
  }, [location.pathname])

  const handleLogout = async () => {
    try {
      await logout()
    } finally {
      clearSession()
      navigate('/connexion', { replace: true })
    }
  }

  const requeteMenu = normaliserRecherche(rechercheMenu.trim())

  const groupesVisibles = useMemo(
    () =>
      navGroups
        .map((group) => {
          const libelleGroupe = t(group.label)
          const groupeCorrespond = requeteMenu !== '' && normaliserRecherche(libelleGroupe).includes(requeteMenu)
          const itemsAutorises = group.items.filter(
            (item) =>
              can(item.permission) &&
              (!('superAdminOnly' in item) || !item.superAdminOnly || user?.is_super_admin) &&
              (!('types' in item) || !typeEcole || (item.types as TypeEcole[]).includes(typeEcole)) &&
              (!('roles' in item) ||
                !item.roles ||
                user?.is_super_admin ||
                (item.roles as string[]).some((r) => user?.roles.includes(r))),
          )
          const items = requeteMenu
            ? itemsAutorises.filter((item) => groupeCorrespond || normaliserRecherche(t(item.label)).includes(requeteMenu))
            : itemsAutorises

          return { ...group, items }
        })
        .filter((group) => group.items.length > 0),
    [can, requeteMenu, t, typeEcole, user?.is_super_admin],
  )

  /**
   * `/eleves` est un préfixe de `/eleves/transferts` : sans ceci, NavLink (en
   * mode préfixe, cf. `end`) activerait les deux à la fois. On ne retient que
   * l'entrée du menu dont le chemin correspond le plus précisément à l'URL.
   */
  const cheminActif = useMemo(() => {
    let meilleur: string | null = null
    for (const group of groupesVisibles) {
      for (const item of group.items) {
        const correspond = location.pathname === item.to || location.pathname.startsWith(`${item.to}/`)
        if (correspond && (!meilleur || item.to.length > meilleur.length)) meilleur = item.to
      }
    }
    return meilleur
  }, [groupesVisibles, location.pathname])

  return (
    <div className="flex h-svh overflow-hidden bg-cream-50">
      {menuOuvert && (
        <div
          className="animate-fade-in fixed inset-0 z-40 bg-navy-900/60 backdrop-blur-sm lg:hidden"
          onClick={() => setMenuOuvert(false)}
          aria-hidden
        />
      )}

      <aside
        className={clsx(
          'fixed inset-y-0 left-0 z-50 flex w-[17rem] flex-none flex-col overflow-y-auto bg-navy-800 text-cream-50 shadow-lifted transition-transform duration-200 ease-out',
          'lg:static lg:z-auto lg:w-64 lg:translate-x-0 lg:shadow-none',
          menuOuvert ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <div className="flex items-center gap-2.5 px-5 py-5">
          <span className="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-gold-500/15 ring-1 ring-gold-500/30">
            <GraduationCap className="h-5 w-5 text-gold-300" />
          </span>
          <span className="font-display text-lg font-bold tracking-tight">{t('app.name')}</span>
          <button
            onClick={() => setMenuOuvert(false)}
            className="ml-auto rounded-lg p-1.5 text-navy-300 transition-colors hover:bg-white/10 hover:text-white lg:hidden"
            aria-label={t('common.close')}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="px-3 pb-3">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400" />
            <input
              value={rechercheMenu}
              onChange={(e) => setRechercheMenu(e.target.value)}
              placeholder={t('nav.searchMenu')}
              className="h-10 w-full rounded-xl border border-white/10 bg-white/8 py-2 pl-9 pr-3 text-sm text-white outline-none transition-colors placeholder:text-navy-400 focus:border-gold-400/50 focus:bg-white/12 focus:ring-4 focus:ring-gold-400/10"
            />
          </div>
        </div>

        <nav className="flex flex-1 flex-col gap-2 px-3 pt-1 pb-4">
          {groupesVisibles.map((group) => (
            <div key={group.label} className="flex flex-col gap-1">
              <button
                type="button"
                aria-expanded={Boolean(requeteMenu) || groupesOuverts[group.label]}
                onClick={() =>
                  setGroupesOuverts((actuel) => ({
                    ...actuel,
                    [group.label]: !actuel[group.label],
                  }))
                }
                className="flex h-8 items-center justify-between gap-2 rounded-lg px-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-navy-400 transition-colors hover:bg-white/5 hover:text-navy-200"
              >
                <span className="truncate">{t(group.label)}</span>
                <ChevronDown
                  className={clsx(
                    'h-3.5 w-3.5 flex-none transition-transform',
                    (requeteMenu || groupesOuverts[group.label]) && 'rotate-180',
                  )}
                />
              </button>
              {(requeteMenu || groupesOuverts[group.label]) && (
                <div className="flex flex-col gap-1">
                  {group.items.map((item) => {
                    const estActif = item.to === cheminActif
                    return (
                      <NavLink
                        key={item.to}
                        to={item.to}
                        end
                        aria-current={estActif ? 'page' : undefined}
                        className={clsx(
                          'group relative flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-colors',
                          estActif
                            ? 'bg-white/10 text-white shadow-[inset_0_1px_0_0_rgb(255_255_255/0.06)]'
                            : 'text-navy-200 hover:bg-white/5 hover:text-white',
                        )}
                      >
                        {estActif && (
                          <span className="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-gold-400" />
                        )}
                        <item.icon
                          className={clsx(
                            'h-[18px] w-[18px] flex-none',
                            estActif ? 'text-gold-300' : 'text-navy-300 group-hover:text-gold-200',
                          )}
                        />
                        <span className="truncate">{t(item.label)}</span>
                      </NavLink>
                    )
                  })}
                </div>
              )}
            </div>
          ))}
          {groupesVisibles.length === 0 && (
            <div className="rounded-xl border border-white/10 px-3.5 py-4 text-sm text-navy-300">
              {t('nav.noMenuFound')}
            </div>
          )}
        </nav>

        <div className="sticky bottom-0 flex items-center gap-3 border-t border-white/10 bg-navy-800 px-4 py-4">
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

      <div className="flex min-h-0 min-w-0 flex-1 flex-col">
        <header className="relative z-30 flex flex-none items-center gap-3 border-b border-navy-100 bg-white/85 px-4 py-3 backdrop-blur-sm sm:px-6">
          <button
            onClick={() => setMenuOuvert(true)}
            className="-ml-1 flex h-9 w-9 flex-none items-center justify-center rounded-xl text-navy-500 transition-colors hover:bg-cream-100 hover:text-navy-800 lg:hidden"
            aria-label={t('nav.openMenu')}
          >
            <Menu className="h-5 w-5" />
          </button>

          <div className="flex min-w-0 flex-1 items-center gap-3">
            <SchoolSwitcher />
          </div>

          <div className="flex flex-none gap-1 rounded-full bg-cream-100 p-1 text-xs font-bold">
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

          <div className="hidden items-center gap-3 xl:flex">
            <div className="text-right text-sm leading-tight">
              <p className="font-semibold text-navy-800">{user?.name}</p>
              <p className="text-xs text-navy-400">{user?.roles.join(', ')}</p>
            </div>
            <span className="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-navy-700 text-xs font-bold text-cream-50">
              {initials(user?.name)}
            </span>
          </div>
        </header>

        <main className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
          <div className="mx-auto max-w-7xl">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}
