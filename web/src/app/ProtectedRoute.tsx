import { useEffect, useRef, type ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuthStore, type AuthUser } from '@/shared/store/authStore'
import { fetchMe } from '@/features/auth/api'

/**
 * Destination de repli d'un compte : le portail parent pour un rôle
 * `parent`, sinon le premier écran que ses privilèges ouvrent réellement.
 * Un compte borné à un seul module (ex. vendeur : point de vente et
 * inventaire, sans `dashboard.view`) ne peut pas se rabattre sur `/` — la
 * garde de permission de cette route le renverrait ici même, en boucle. Le
 * profil, sans permission requise, reste le seul repli garanti pour tout
 * compte authentifié.
 */
function redirectionParDefaut(user: AuthUser | null | undefined): string {
  if (user?.roles.includes('parent')) return '/parent'

  const peut = (permission: string) => Boolean(user?.is_super_admin || user?.permissions.includes(permission))

  if (peut('dashboard.view')) return '/'
  if (peut('point_de_vente.view')) return '/point-de-vente'
  if (peut('inventaire.view')) return '/inventaire'

  return '/profil'
}

export function ProtectedRoute({
  children,
  permission,
  enseignantOnly = false,
  superAdminOnly = false,
  masquerPourTitulaire = false,
  parentOnly = false,
  personnelOnly = false,
  chefDepartementOnly = false,
  professeurPrincipalOnly = false,
  animateurNiveauOnly = false,
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
  /**
   * Réservé aux comptes qui dirigent au moins un département — masquer le
   * lien du menu n'empêcherait pas d'y entrer par une URL directe, et l'API
   * y répond de toute façon 403 (cf. EnseignantController::monDepartement()).
   */
  chefDepartementOnly?: boolean
  /**
   * Réservé aux comptes professeur principal d'au moins une classe — masquer
   * le lien du menu n'empêcherait pas d'y entrer par une URL directe, et
   * l'API y répond de toute façon 403 (cf. EnseignantController::maClasseProfPrincipal()).
   */
  professeurPrincipalOnly?: boolean
  /**
   * Réservé aux comptes qui animent au moins un niveau scolaire
   * (primaire/maternelle) — pendant de `chefDepartementOnly` pour ces
   * cycles (cf. EnseignantController::monNiveau()).
   */
  animateurNiveauOnly?: boolean
}) {
  const { token, user, can, aAttribution, activeSchool, refreshUser } = useAuthStore()
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
  if (parentOnly && !estParent) return <Navigate to={redirectionParDefaut(user)} replace />
  if (!parentOnly && estParent) return <Navigate to={redirectionParDefaut(user)} replace />

  if (superAdminOnly && !user?.is_super_admin) return <Navigate to={redirectionParDefaut(user)} replace />
  if (permission && !can(permission)) return <Navigate to={redirectionParDefaut(user)} replace />
  if (enseignantOnly && !user?.est_enseignant) return <Navigate to={redirectionParDefaut(user)} replace />
  if (personnelOnly && !user?.est_personnel) return <Navigate to={redirectionParDefaut(user)} replace />
  if (chefDepartementOnly && !aAttribution('chef_departement')) return <Navigate to={redirectionParDefaut(user)} replace />
  if (professeurPrincipalOnly && !aAttribution('professeur_principal')) return <Navigate to={redirectionParDefaut(user)} replace />
  if (animateurNiveauOnly && !aAttribution('animateur_niveau')) return <Navigate to={redirectionParDefaut(user)} replace />

  const typeEcole = activeSchool()?.type
  const estTitulaireDeClasse = Boolean(user?.est_enseignant) && (typeEcole === 'primaire' || typeEcole === 'maternelle')
  if (masquerPourTitulaire && estTitulaireDeClasse) return <Navigate to={redirectionParDefaut(user)} replace />

  return <>{children}</>
}
