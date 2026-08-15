import { useEffect, useRef, type ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuthStore } from '@/shared/store/authStore'
import { fetchMe } from '@/features/auth/api'

export function ProtectedRoute({
  children,
  permission,
  roles,
  superAdminOnly = false,
}: {
  children: ReactNode
  permission?: string
  /**
   * Restreint aux comptes portant l'un de ces rôles — y compris le super
   * admin, qui a bien la permission technique mais n'est pas titulaire d'une
   * classe : lui montrer « Ma journée » n'aurait pas de sens.
   */
  roles?: string[]
  superAdminOnly?: boolean
}) {
  const { token, user, can, refreshUser } = useAuthStore()
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
  if (superAdminOnly && !user?.is_super_admin) return <Navigate to="/" replace />
  if (permission && !can(permission)) return <Navigate to="/" replace />
  if (roles && !roles.some((r) => user?.roles.includes(r))) return <Navigate to="/" replace />

  return <>{children}</>
}
