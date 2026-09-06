import { useEffect, useState } from 'react'

/**
 * Abonnement au statut `electron-updater` relayé par le processus principal
 * (cf. `web/desktop/src/main.cjs`, `configurerAutoUpdate`). `undefined` tant
 * qu'aucun événement n'est encore arrivé — l'appelant doit le traiter comme
 * « en cours de vérification », pas comme une absence de mise à jour.
 */
export function useDesktopUpdate() {
  const [statut, setStatut] = useState<StatutMiseAJourDesktop | undefined>(undefined)

  useEffect(() => {
    if (!window.desktop) return
    return window.desktop.onUpdateStatus(setStatut)
  }, [])

  const verifier = async () => {
    if (!window.desktop) return
    await window.desktop.checkForUpdates()
  }

  const redemarrerPourInstaller = async () => {
    if (!window.desktop) return
    await window.desktop.quitAndInstall()
  }

  return { statut, verifier, redemarrerPourInstaller }
}
