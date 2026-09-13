import { useEffect, useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { fetchStatutSync } from '@/features/desktop/api'
import { PremiereSynchronisationModal } from '@/features/desktop/PremiereSynchronisationModal'
import { useAuthStore } from '@/shared/store/authStore'

/**
 * `LoginPage` force déjà `PremiereSynchronisationModal` tant que le premier
 * clonage n'est pas complet — mais uniquement au moment de la CONNEXION.
 * `ProtectedRoute`, lui, ne vérifie que la présence d'un jeton : une session
 * persistée (`authStore` utilise `zustand/persist`) d'un lancement précédent
 * rouvre directement sur le tableau de bord au démarrage suivant, sans
 * jamais repasser par ce contrôle — observé en conditions réelles : un
 * clonage interrompu laissait ensuite l'utilisateur consulter des écrans
 * avec des données partielles (ex. 0 élève affiché) sans le moindre
 * avertissement.
 *
 * Ce composant referme cet écart : à chaque montage de la coquille
 * authentifiée (donc à chaque lancement de l'application, pas seulement à la
 * connexion), il revérifie `clonage_initial_complet` et bloque avec la même
 * modale si besoin. N'a d'effet qu'en desktop (`window.desktop` absent sur
 * le site web classique, où cette notion n'existe pas).
 */
export function DesktopClonageGate({ children }: { children: ReactNode }) {
  const token = useAuthStore((s) => s.token)
  const clearSession = useAuthStore((s) => s.clearSession)
  const navigate = useNavigate()
  const [etat, setEtat] = useState<'verification' | 'bloque' | 'ok'>(
    window.desktop ? 'verification' : 'ok',
  )

  useEffect(() => {
    if (!window.desktop) return

    let annule = false

    fetchStatutSync()
      .then((statut) => {
        if (!annule) setEtat(statut.clonage_initial_complet ? 'ok' : 'bloque')
      })
      .catch(() => {
        // Un aléa réseau/local sur ce simple contrôle ne doit pas bloquer
        // l'accès à une application par ailleurs fonctionnelle — au pire,
        // un clonage réellement incomplet sera rattrapé par la boucle de
        // synchronisation périodique ou détecté à la prochaine connexion.
        if (!annule) setEtat('ok')
      })

    return () => {
      annule = true
    }
    // Ne se rejoue qu'au changement de compte (jeton) : une fois "ok" pour
    // cette session, pas besoin de revérifier à chaque navigation interne.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token])

  // « Utiliser un autre compte » n'a de sens, ici, qu'en repartant de la
  // connexion — laisser passer un clonage encore incomplet viderait le
  // contrôle de tout son sens.
  const seDeconnecter = () => {
    clearSession()
    navigate('/connexion', { replace: true })
  }

  if (etat === 'bloque') {
    return <PremiereSynchronisationModal onTermine={() => setEtat('ok')} onAnnuler={seDeconnecter} />
  }

  // 'verification' : un bref instant à chaque lancement, le temps d'un seul
  // appel local — un écran vide plutôt qu'un flash du tableau de bord suivi
  // aussitôt de la modale si le clonage s'avère incomplet.
  if (etat === 'verification') return null

  return <>{children}</>
}
