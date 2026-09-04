import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { Users, FilePlus2, ClipboardList, LogOut, Megaphone, ArrowLeftRight } from 'lucide-react'
import { clsx } from 'clsx'
import logoWordmark from '@/assets/logo-wordmark.png'
import { useAuthStore } from '@/shared/store/authStore'
import { logout } from '@/features/auth/api'

const liens = [
  { to: '/parent', fr: 'Mes enfants', en: 'My children', icon: Users, end: true },
  { to: '/parent/annonces', fr: 'Annonces', en: 'Announcements', icon: Megaphone, end: true },
  { to: '/parent/preinscription/nouveau', fr: 'Inscrire un enfant', en: 'Register a child', icon: FilePlus2, end: true },
  { to: '/parent/preinscriptions', fr: 'Mes démarches', en: 'My requests', icon: ClipboardList, end: true },
]

function initiales(nom?: string) {
  if (!nom) return '?'
  const mots = nom.trim().split(/\s+/)
  return ((mots[0]?.[0] ?? '') + (mots[1]?.[0] ?? '')).toUpperCase()
}

/**
 * Coquille du portail parent — volontairement distincte de `AppLayout` : un
 * parent n'a que quatre destinations, pas les quarante du personnel. Une
 * barre horizontale simple évite de reconstruire toute la mécanique de
 * sidebar/permissions pour un menu aussi court.
 */
export function ParentLayout() {
  const navigate = useNavigate()
  const { user, clearSession } = useAuthStore()

  const handleLogout = async () => {
    try {
      await logout()
    } finally {
      clearSession()
      navigate('/connexion', { replace: true })
    }
  }

  return (
    <div className="flex h-svh min-w-0 flex-col overflow-hidden bg-cream-50 sm:min-h-svh sm:h-auto sm:overflow-x-clip sm:overflow-y-visible">
      <header className="flex-none border-b border-navy-100 bg-navy-800 text-cream-50">
        <div className="mx-auto flex min-w-0 max-w-5xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
          <div className="flex min-w-0 items-center gap-2.5">
            <span className="flex h-9 flex-none items-center justify-center rounded-xl bg-white px-2 py-1.5 shadow-soft">
              <img src={logoWordmark} alt="Elites" className="h-5 w-auto object-contain" />
            </span>
            <span className="truncate font-display text-base font-bold tracking-tight">Espace parent / Parent portal</span>
          </div>

          <nav
            className="fixed inset-x-0 bottom-0 z-40 grid h-16 border-t border-navy-600 bg-navy-800/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_24px_rgba(8,21,43,0.16)] backdrop-blur sm:static sm:flex sm:h-auto sm:w-auto sm:flex-1 sm:flex-wrap sm:justify-center sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none sm:backdrop-blur-none"
            style={{ gridTemplateColumns: `repeat(${liens.length}, minmax(0, 1fr))` }}
          >
            {liens.map((lien) => (
              <NavLink
                key={lien.to}
                to={lien.to}
                end={lien.end}
                className={({ isActive }) =>
                  clsx(
                    'flex min-w-0 flex-col items-center justify-center gap-0.5 px-1 py-1 text-center text-[10px] font-semibold leading-tight transition-colors sm:flex-row sm:justify-start sm:gap-1.5 sm:rounded-lg sm:px-3 sm:py-1.5 sm:text-xs',
                    isActive ? 'bg-white/10 text-white' : 'text-navy-200 hover:bg-white/5 hover:text-white',
                  )
                }
              >
                <lien.icon className="h-4 w-4 flex-none sm:h-3.5 sm:w-3.5" />
                <span className="flex max-w-full flex-col items-center leading-tight sm:hidden">
                  <span className="truncate">{lien.fr}</span>
                  <span className="truncate opacity-75">{lien.en}</span>
                </span>
                <span className="hidden max-w-full truncate sm:inline">
                  {lien.fr} / {lien.en}
                </span>
              </NavLink>
            ))}
          </nav>

          <div className="flex flex-none items-center gap-2">
            {user?.est_personnel && (
              <button
                onClick={() => navigate('/')}
                title="Retour à mon espace personnel / Back to my staff account"
                className="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-navy-300 transition-colors hover:bg-white/10 hover:text-white"
              >
                <ArrowLeftRight className="h-4 w-4" />
              </button>
            )}
            <NavLink
              to="/parent/profil"
              title="Mon compte / My account"
              className={({ isActive }) =>
                clsx(
                  'flex h-8 w-8 flex-none items-center justify-center rounded-full bg-gold-500/20 text-xs font-bold text-gold-200 ring-1 ring-gold-400/30 transition-colors hover:bg-gold-500/30',
                  isActive && 'ring-2 ring-white/60',
                )
              }
            >
              {initiales(user?.name)}
            </NavLink>
            <button
              onClick={handleLogout}
              title="Déconnexion / Logout"
              className="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-navy-300 transition-colors hover:bg-white/10 hover:text-white"
            >
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto min-h-0 min-w-0 w-full max-w-5xl flex-1 overflow-x-clip overflow-y-auto overscroll-contain px-4 pb-24 pt-6 sm:min-h-auto sm:overflow-visible sm:px-6 sm:py-6">
        <Outlet />
      </main>
    </div>
  )
}
