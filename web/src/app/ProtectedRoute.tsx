import { useEffect, useRef, type ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuthStore } from '@/shared/store/authStore'
import { fetchMe } from '@/features/auth/api'

export function ProtectedRoute({
  children,
  permission,
  enseignantOnly = false,
  superAdminOnly = false,
  masquerPourTitulaire = false,
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

  if (superAdminOnly && !user?.is_super_admin) return <Navigate to="/" replace />
  if (permission && !can(permission)) return <Navigate to="/" replace />
  if (enseignantOnly && !user?.est_enseignant) return <Navigate to="/" replace />

  const typeEcole = activeSchool()?.type
  const estTitulaireDeClasse = Boolean(user?.est_enseignant) && (typeEcole === 'primaire' || typeEcole === 'maternelle')
  if (masquerPourTitulaire && estTitulaireDeClasse) return <Navigate to="/" replace />

  return <>{children}</>
}
