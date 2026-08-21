import { useEffect, useRef, type ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuthStore } from '@/shared/store/authStore'
import { fetchMe } from '@/features/auth/api'

/**
 * Destination de repli d'un compte : le portail parent pour un rôle
 * `parent`, le tableau de bord du personnel sinon. Un compte parent ne porte
 * pas `dashboard.view` — le rediriger vers `/` bouclerait indéfiniment sur la
 * garde de permission de cette même route.
 */
function redirectionParDefaut(roles: string[] | undefined): string {
  return roles?.includes('parent') ? '/parent' : '/'
}

export function ProtectedRoute({
  children,
  permission,
  enseignantOnly = false,
  superAdminOnly = false,
  masquerPourTitulaire = false,
  parentOnly = false,
  personnelOnly = false,
}: {
  children: ReactNode
  permission?: string
  /**
   * Restreint aux comptes exerçant une fonction d'enseignement — y compris le
   * super admin, qui a bien la permission technique mais n'est titulaire
   * d'aucune classe : lui montrer « Ma journée » n'aurait pas de sens.
   */
  enseignantOnly?: boolean
  superAdminOnly?: boolean
  /**
   * Ferme cette route aux titulaires de primaire/maternelle : leur périmètre
   * se limite à « Ma classe », pas à la liste complète des classes/élèves —
   * sans ce garde-fou, masquer le lien du menu n'empêcherait pas d'y entrer
   * par une URL directe.
   */
  masquerPourTitulaire?: boolean
  /** Réservé au portail parent — un compte du personnel n'y a rien à faire. */
  parentOnly?: boolean
  /**
   * Réservé aux comptes portant une fiche personnel : l'espace libre-service
   * n'a rien à montrer à un compte purement administratif, et l'API y répond
   * de toute façon 404 (cf. PersonnelEspaceController::moi()).
   */
  personnelOnly?: boolean
}) {
  const { token, user, can, activeSchool, refreshUser } = useAuthStore()
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

  // Mot de passe provisoire : l'API refuse déjà tout le reste (423), autant
  // conduire directement à la seule page utile plutôt qu'afficher des écrans
  // vides suivis d'un message d'erreur.
  if (user?.doit_changer_mot_de_passe) return <Navigate to="/mot-de-passe" replace />

  // Le portail parent est fermé au personnel, et l'inverse aussi : partager
  // une même route entre les deux enverrait un parent chercher un menu de
  // quarante entrées qui ne le concernent pas, ou un agent chercher les
  // écrans d'un rôle qu'il ne porte pas.
  const estParent = Boolean(user?.roles.includes('parent'))
  if (parentOnly && !estParent) return <Navigate to={redirectionParDefaut(user?.roles)} replace />
  if (!parentOnly && estParent) return <Navigate to={redirectionParDefaut(user?.roles)} replace />

  if (superAdminOnly && !user?.is_super_admin) return <Navigate to={redirectionParDefaut(user?.roles)} replace />
  if (permission && !can(permission)) return <Navigate to={redirectionParDefaut(user?.roles)} replace />
  if (enseignantOnly && !user?.est_enseignant) return <Navigate to={redirectionParDefaut(user?.roles)} replace />
  if (personnelOnly && !user?.est_personnel) return <Navigate to={redirectionParDefaut(user?.roles)} replace />

  const typeEcole = activeSchool()?.type
  const estTitulaireDeClasse = Boolean(user?.est_enseignant) && (typeEcole === 'primaire' || typeEcole === 'maternelle')
  if (masquerPourTitulaire && estTitulaireDeClasse) return <Navigate to={redirectionParDefaut(user?.roles)} replace />

  return <>{children}</>
}
