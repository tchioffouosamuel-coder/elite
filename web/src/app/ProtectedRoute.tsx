import { type ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuthStore } from '@/shared/store/authStore'

export function ProtectedRoute({ children, permission }: { children: ReactNode; permission?: string }) {
  const { token, can } = useAuthStore()

  if (!token) return <Navigate to="/connexion" replace />
  if (permission && !can(permission)) return <Navigate to="/" replace />

  return <>{children}</>
}
