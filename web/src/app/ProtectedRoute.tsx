import { useEffect, type ReactNode } from 'react'
import { peutVoirFinancesEcole } from '@/app/financesEcole'
import { Navigate } from 'react-router-dom'
import { useAuthStore, type AuthUser } from '@/shared/store/authStore'
import { fetchMe } from '@/features/auth/api'

/** Dernier jeton dont le profil a été resynchronisé (cf. ProtectedRoute). */
let jetonDejaRafraichi: string | null = null

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
  // Même règle que les gardes ci-dessous : les portails parent et élève ne
  // sont le repli que d'un compte qui n'est QUE ça. Sans ce test, un agent
  // ou un super administrateur qui est aussi tuteur retombait au portail
  // parent à chaque garde qui le refusait, et n'atteignait plus aucun écran
  // de sa fonction.
  const estMetier = Boolean(user?.est_personnel || user?.is_super_admin)
  if (!estMetier && user?.roles.includes('eleve')) return '/eleve'
  if (!estMetier && user?.roles.includes('parent')) return '/parent'

  const peut = (permission: string) => Boolean(user?.is_super_admin || user?.permissions.includes(permission))

  // Avant `dashboard.view` : un chauffeur n'en porte pas, mais la direction
  // qui le remplacerait au volant, si. L'accueil d'un chauffeur est sa
  // tournée, jamais le tableau de bord d'établissement — cf. est_chauffeur.
  if (user?.est_chauffeur && peut('bus.view')) return '/chauffeur'
  if (peut('dashboard.view')) return '/'
  if (peut('point_de_vente.view')) return '/point-de-vente'
  if (peut('inventaire.view')) return '/inventaire'
  if (peut('bus.view')) return '/bus/vehicules'

  return '/profil'
}

export function ProtectedRoute({
  children,
  permission,
  enseignantOnly = false,
  enseignantPrimaireOnly = false,
  superAdminOnly = false,
  masquerPourTitulaire = false,
  masquerPourVendeur = false,
  chauffeurOnly = false,
  parentOnly = false,
  eleveOnly = false,
  personnelOnly = false,
  chefDepartementOnly = false,
  professeurPrincipalOnly = false,
  animateurNiveauOnly = false,
  financesEcole = false,
}: {
  children: ReactNode
  permission?: string
  /**
   * Restreint aux comptes exerçant une fonction d'enseignement — y compris le
   * super admin, qui a bien la permission technique mais n'est titulaire
   * d'aucune classe : lui montrer « Ma journée » n'aurait pas de sens.
   */
  enseignantOnly?: boolean
  /** Réservé aux enseignants du primaire et de la maternelle. */
  enseignantPrimaireOnly?: boolean
  superAdminOnly?: boolean
  /**
   * Ferme cette route aux titulaires de primaire/maternelle : leur périmètre
   * se limite à « Ma classe », pas à la liste complète des classes/élèves —
   * sans ce garde-fou, masquer le lien du menu n'empêcherait pas d'y entrer
   * par une URL directe.
   */
  masquerPourTitulaire?: boolean
  /**
   * Ferme cette route au rôle vendeur : il garde `eleves.view` pour peupler
   * le sélecteur d'élève au comptoir (vente à crédit), mais l'écran complet
   * (fiche, liste, identification…) est hors de son périmètre — masquer le
   * lien du menu n'empêcherait pas d'y entrer par une URL directe.
   */
  masquerPourVendeur?: boolean
  /**
   * Réservé aux chauffeurs de la flotte : « Ma tournée » et l'itinéraire
   * n'ont de sens que pour qui conduit. Un économe ou un membre de la
   * direction porte les mêmes `bus.view` mais gère la flotte entière — ses
   * écrans sont /bus/*, pas ceux-ci.
   */
  chauffeurOnly?: boolean
  /** Réservé au portail parent — un compte du personnel n'y a rien à faire. */
  parentOnly?: boolean
  /** Réservé au portail élève — même principe que `parentOnly`, pour le rôle `eleve`. */
  eleveOnly?: boolean
  /**
   * Réservé aux comptes portant une fiche personnel : l'espace libre-service
   * n'a rien à montrer à un compte purement administratif, et l'API y répond
   * de toute façon 404 (cf. PersonnelEspaceController::moi()).
   */
  personnelOnly?: boolean
  /**
   * Finances de l'établissement (caisse, tarifs, dépenses…) : fermées à
   * l'enseignant qui ne tient pas la caisse — cf. `peutVoirFinancesEcole`.
   */
  financesEcole?: boolean
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

  // Le profil vient du stockage local et peut dater d'une version antérieure de
  // l'API (permissions ou établissements accessibles modifiés depuis). On le
  // resynchronise une fois par jeton et par chargement de l'application — pas
  // à chaque montage : chaque route enveloppe sa page dans son propre
  // ProtectedRoute, et un simple `useRef` relançait donc `/auth/me` (une
  // quinzaine de requêtes SQL côté API) à chaque changement de page.
  useEffect(() => {
    if (!token || jetonDejaRafraichi === token) return
    jetonDejaRafraichi = token

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
  // écrans d'un rôle qu'il ne porte pas. Un compte fusionné (agent qui est
  // aussi tuteur, cf. FusionnerComptesPersonnelParent) porte les deux à la
  // fois : il garde son menu personnel par défaut, et n'est jamais forcé
  // vers /parent — seul un compte purement parent l'est.
  // Un super administrateur n'a pas forcément de fiche personnel : sans lui
  // ici, le compte d'un super admin tuteur de ses enfants était traité comme
  // un parent ordinaire et perdait toute l'administration.
  const estMetier = Boolean(user?.est_personnel || user?.is_super_admin)

  const estParent = Boolean(user?.roles.includes('parent'))
  const estParentSeul = estParent && !estMetier
  if (parentOnly && !estParent) return <Navigate to={redirectionParDefaut(user)} replace />
  if (!parentOnly && estParentSeul) return <Navigate to={redirectionParDefaut(user)} replace />

  // Symétrique du parent : un compte élève fusionné garde lui aussi son
  // interface de travail. C'est aussi ce qui évite la boucle, puisque
  // `redirectionParDefaut` ne renvoie plus un tel compte vers /eleve.
  const estEleve = Boolean(user?.roles.includes('eleve'))
  const estEleveSeul = estEleve && !estMetier
  if (eleveOnly && !estEleve) return <Navigate to={redirectionParDefaut(user)} replace />
  if (!eleveOnly && estEleveSeul) return <Navigate to={redirectionParDefaut(user)} replace />

  if (superAdminOnly && !user?.is_super_admin) return <Navigate to={redirectionParDefaut(user)} replace />
  if (permission && !can(permission)) return <Navigate to={redirectionParDefaut(user)} replace />
  if (enseignantOnly && !user?.est_enseignant) return <Navigate to={redirectionParDefaut(user)} replace />
  if (enseignantPrimaireOnly && !user?.is_super_admin && !can('progression.update') && (!user?.est_enseignant || !['primaire', 'maternelle'].includes(activeSchool()?.type ?? ''))) {
    return <Navigate to={redirectionParDefaut(user)} replace />
  }
  if (personnelOnly && !user?.est_personnel) return <Navigate to={redirectionParDefaut(user)} replace />
  if (financesEcole && !peutVoirFinancesEcole(user, can)) return <Navigate to={redirectionParDefaut(user)} replace />
  if (chefDepartementOnly && !aAttribution('chef_departement')) return <Navigate to={redirectionParDefaut(user)} replace />
  if (professeurPrincipalOnly && !aAttribution('professeur_principal')) return <Navigate to={redirectionParDefaut(user)} replace />
  if (animateurNiveauOnly && !aAttribution('animateur_niveau')) return <Navigate to={redirectionParDefaut(user)} replace />

  const typeEcole = activeSchool()?.type
  const estTitulaireDeClasse = Boolean(user?.est_enseignant) && (typeEcole === 'primaire' || typeEcole === 'maternelle')
  if (masquerPourTitulaire && estTitulaireDeClasse) return <Navigate to={redirectionParDefaut(user)} replace />
  if (masquerPourVendeur && user?.est_vendeur) return <Navigate to={redirectionParDefaut(user)} replace />
  if (chauffeurOnly && !user?.est_chauffeur) return <Navigate to={redirectionParDefaut(user)} replace />

  return <>{children}</>
}
