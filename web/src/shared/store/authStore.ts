import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export interface EcoleAccessible {
  id: number
  name: string
  code: string
  type: 'maternelle' | 'primaire' | 'secondaire'
  /** Sert au filigrane de l'interface — livré à tous les comptes, pas seulement aux gestionnaires. */
  logo_url?: string | null
}

export interface AuthUser {
  id: number
  name: string
  email: string
  phone?: string | null
  school_id: number | null
  niveau_id: number | null
  roles: string[]
  is_super_admin: boolean
  /** Compte ouvert avec le mot de passe commun : rien d'autre n'est accessible tant qu'il n'est pas remplacé. */
  doit_changer_mot_de_passe?: boolean
  /** Privilèges effectifs : attribution directe, rôle et fonction confondus. */
  permissions: string[]
  /** Fonction du référentiel, quand le compte représente un agent. */
  fonction?: string | null
  /**
   * Exerce une fonction d'enseignement (cf. User::estEnseignant côté API) —
   * distinct des rôles/permissions : un censeur ou un économe peut porter
   * `appel.manage` sans être enseignant pour autant.
   */
  est_enseignant?: boolean
  ecoles_accessibles: EcoleAccessible[]
}

interface AuthState {
  token: string | null
  user: AuthUser | null
  /**
   * Établissement sur lequel l'interface travaille. Pour un compte rattaché à
   * une école c'est toujours la sienne ; le super administrateur en change
   * depuis le sélecteur de la barre supérieure.
   */
  activeSchoolId: number | null
  setSession: (token: string, user: AuthUser) => void
  /** Rafraîchit le profil sans toucher au jeton, en réparant l'école active. */
  refreshUser: (user: AuthUser) => void
  setActiveSchool: (schoolId: number) => void
  clearSession: () => void
  can: (permission: string) => boolean
  activeSchool: () => EcoleAccessible | null
}

/** École vers laquelle diriger le compte à la connexion. */
function ecoleParDefaut(user: AuthUser): number | null {
  return user.school_id ?? user.ecoles_accessibles?.[0]?.id ?? null
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      user: null,
      activeSchoolId: null,
      setSession: (token, user) => set({ token, user, activeSchoolId: ecoleParDefaut(user) }),
      refreshUser: (user) => {
        const { activeSchoolId } = get()
        const encoreAccessible = user.ecoles_accessibles?.some((e) => e.id === activeSchoolId)

        set({ user, activeSchoolId: encoreAccessible ? activeSchoolId : ecoleParDefaut(user) })
      },
      setActiveSchool: (schoolId) => {
        // Refuse une école hors du périmètre du compte : l'API la rejetterait
        // de toute façon, autant ne pas basculer l'interface dans le vide.
        if (get().user?.ecoles_accessibles?.some((e) => e.id === schoolId)) {
          set({ activeSchoolId: schoolId })
        }
      },
      clearSession: () => set({ token: null, user: null, activeSchoolId: null }),
      can: (permission) => {
        const user = get().user
        if (!user) return false
        return user.is_super_admin || user.permissions.includes(permission)
      },
      activeSchool: () => {
        const { user, activeSchoolId } = get()
        return user?.ecoles_accessibles?.find((e) => e.id === activeSchoolId) ?? null
      },
    }),
    {
      name: 'elites-school-auth',
      // Le profil persisté a gagné `ecoles_accessibles` et `activeSchoolId` avec
      // le multi-école. Une session ouverte avant ce changement conserverait un
      // profil sans ces champs, et le sélecteur d'établissement resterait inerte.
      // Incrémenter la version force la reconnexion plutôt que de traîner une
      // forme périmée.
      version: 2,
      migrate: () => ({ token: null, user: null, activeSchoolId: null }) as Partial<AuthState>,
    },
  ),
)
