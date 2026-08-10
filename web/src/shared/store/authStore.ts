import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export interface AuthUser {
  id: number
  name: string
  email: string
  school_id: number | null
  niveau_id: number | null
  roles: string[]
  is_super_admin: boolean
  permissions: string[]
}

interface AuthState {
  token: string | null
  user: AuthUser | null
  setSession: (token: string, user: AuthUser) => void
  clearSession: () => void
  can: (permission: string) => boolean
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      user: null,
      setSession: (token, user) => set({ token, user }),
      clearSession: () => set({ token: null, user: null }),
      can: (permission) => {
        const user = get().user
        if (!user) return false
        return user.is_super_admin || user.permissions.includes(permission)
      },
    }),
    { name: 'elites-school-auth' },
  ),
)
