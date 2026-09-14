import { useEffect, useRef, useState, type ReactNode, type FormEvent } from 'react'
import { createPortal } from 'react-dom'
import { useTranslation } from 'react-i18next'
import { Lock, Eye, EyeOff, LogOut } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { login, fetchMe, logout } from '@/features/auth/api'
import { connecterSessionDesktop } from '@/features/auth/desktopProvisioning'
import { useAuthStore, type AuthUser } from '@/shared/store/authStore'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import type { ApiError } from '@/shared/types/api'

/** 5 minutes sans la moindre interaction déclenchent l'écran de veille. */
const DELAI_INACTIVITE_MS = 5 * 60 * 1000

const estDesktop = Boolean(window.desktop)

const EVENEMENTS_ACTIVITE = ['mousemove', 'mousedown', 'keydown', 'wheel', 'touchstart', 'scroll'] as const

/**
 * Verrouille l'application après 5 minutes d'inactivité — un poste laissé
 * sans surveillance donne sinon un accès complet aux données élèves et
 * financières à quiconque s'assoit devant. Le verrouillage se lève en
 * ressaisissant le mot de passe du compte ouvert, jamais par un simple clic :
 * un voile purement visuel ne protégerait rien.
 *
 * L'application continue de tourner derrière l'écran de veille (aucun
 * démontage de `children`) : aucune donnée en cours de saisie n'est perdue au
 * déverrouillage.
 */
export function IdleLockGate({ children }: { children: ReactNode }) {
  const navigate = useNavigate()
  const token = useAuthStore((s) => s.token)
  const user = useAuthStore((s) => s.user)
  const setSession = useAuthStore((s) => s.setSession)
  const clearSession = useAuthStore((s) => s.clearSession)

  const [verrouille, setVerrouille] = useState(false)
  const derniereActivite = useRef(Date.now())

  // Rien à surveiller tant qu'aucun compte n'est ouvert — `ProtectedRoute`
  // s'occupe déjà de rediriger ce cas vers la connexion.
  useEffect(() => {
    if (!token) return

    const noterActivite = () => {
      derniereActivite.current = Date.now()
    }

    for (const evenement of EVENEMENTS_ACTIVITE) {
      window.addEventListener(evenement, noterActivite, { passive: true })
    }

    // Un `setInterval` plutôt qu'un `setTimeout` réarmé à chaque frappe :
    // dix mille resets d'un minuteur pour lire son courrier n'apportent rien
    // qu'une vérification à la seconde n'obtienne déjà, avec beaucoup moins
    // de travail.
    const intervalle = window.setInterval(() => {
      if (Date.now() - derniereActivite.current >= DELAI_INACTIVITE_MS) {
        setVerrouille(true)
      }
    }, 1000)

    return () => {
      for (const evenement of EVENEMENTS_ACTIVITE) {
        window.removeEventListener(evenement, noterActivite)
      }
      window.clearInterval(intervalle)
    }
  }, [token])

  if (!token || !user) return <>{children}</>

  const seDeconnecter = async () => {
    try {
      await logout()
    } finally {
      clearSession()
      navigate('/connexion', { replace: true })
    }
  }

  const apresDeverrouillage = () => {
    derniereActivite.current = Date.now()
    setVerrouille(false)
  }

  return (
    <>
      {children}
      {verrouille &&
        createPortal(
          <EcranDeVeille
            nom={user.name}
            email={user.email}
            onDeverrouille={apresDeverrouillage}
            onDeconnexion={seDeconnecter}
            onSession={setSession}
          />,
          document.body,
        )}
    </>
  )
}

function EcranDeVeille({
  nom,
  email,
  onDeverrouille,
  onDeconnexion,
  onSession,
}: {
  nom: string
  email: string
  onDeverrouille: () => void
  onDeconnexion: () => void
  onSession: (token: string, user: AuthUser) => void
}) {
  const { t } = useTranslation()
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [verification, setVerification] = useState(false)

  const initiales = (nom.trim().split(/\s+/).slice(0, 2).map((p) => p[0] ?? '').join('') || '?').toUpperCase()

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setErreur(null)
    setVerification(true)

    try {
      if (estDesktop) {
        const session = await connecterSessionDesktop({ identifiant: email, password })
        if (!session) throw new Error(t('auth.error_invalid'))
        onSession(session.token, session.user)
      } else {
        const { token } = await login({ identifiant: email, password })
        const utilisateur = await fetchMe()
        onSession(token, utilisateur)
      }

      onDeverrouille()
    } catch (err) {
      setErreur((err as ApiError).message || t('auth.error_invalid'))
    } finally {
      setVerification(false)
    }
  }

  return (
    <div
      className="animate-fade-in fixed inset-0 z-[100] flex flex-col items-center justify-center gap-8 bg-navy-900/95 backdrop-blur-md"
      role="dialog"
      aria-modal="true"
    >
      <div className="flex flex-col items-center gap-3 text-center">
        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20">
          <Lock className="h-6 w-6 text-gold-300" />
        </span>
        <h1 className="font-display text-xl font-bold tracking-tight text-white">{t('auth.lock_title')}</h1>
        <p className="max-w-xs text-sm text-navy-200">{t('auth.lock_subtitle')}</p>
      </div>

      <form onSubmit={onSubmit} className="flex w-full max-w-xs flex-col gap-4 rounded-2xl border border-white/10 bg-white p-6 shadow-lifted">
        <div className="flex flex-col items-center gap-2">
          <span className="flex h-14 w-14 items-center justify-center rounded-full bg-navy-800 text-lg font-bold text-white">
            {initiales}
          </span>
          <div className="text-center">
            <p className="text-sm font-semibold text-navy-900">{nom}</p>
            <p className="text-xs text-navy-400">{email}</p>
          </div>
        </div>

        <Input
          label={t('auth.password')}
          type={showPassword ? 'text' : 'password'}
          icon={Lock}
          autoFocus
          autoComplete="current-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          endAdornment={(
            <button
              type="button"
              onClick={() => setShowPassword((v) => !v)}
              aria-label={showPassword ? t('auth.hide_password') : t('auth.show_password')}
              className="flex h-6 w-6 items-center justify-center text-navy-400 transition-colors hover:text-navy-600"
            >
              {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          )}
        />

        {erreur && <p className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-600">{erreur}</p>}

        <Button type="submit" disabled={verification || password === ''} className="w-full">
          {t('auth.unlock')}
        </Button>

        <button
          type="button"
          onClick={onDeconnexion}
          className="flex items-center justify-center gap-1.5 text-xs font-semibold text-navy-400 transition-colors hover:text-navy-700"
        >
          <LogOut className="h-3.5 w-3.5" />
          {t('auth.use_another_account')}
        </button>
      </form>
    </div>
  )
}
