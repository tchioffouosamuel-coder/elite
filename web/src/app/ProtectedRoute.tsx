import { useEffect, useRef, type ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuthStore } from '@/shared/store/authStore'
import { fetchMe } from '@/features/auth/api'

export function ProtectedRoute({ children, permission }: { children: ReactNode; permission?: string }) {
  const { token, can, refreshUser } = useAuthStore()
  const dejaRafraichi = useRef(false)

  // Le profil vient du stockage local et peut dater d'une version antérieure de
  // l'API (permissions ou établissements accessibles modifiés depuis). On le
  // resynchronise une fois au montage plutôt que de faire confiance au cache.
  useEffect(() => {
    if (!token || dejaRafraichi.current) return
    dejaRafraichi.current = true

    fetchMe()
      .then(refreshUser)
      .catch(() => {
        // Un jeton invalide est déjà traité par l'intercepteur HTTP (401 →
        // fermeture de session) ; inutile d'agir une seconde fois ici.
      })
  }, [token, refreshUser])

  if (!token) return <Navigate to="/connexion" replace />
  if (permission && !can(permission)) return <Navigate to="/" replace />

  return <>{children}</>
}
