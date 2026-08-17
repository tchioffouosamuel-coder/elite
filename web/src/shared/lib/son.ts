/**
 * Petit « ding » de notification synthétisé à la volée (deux tons brefs) —
 * évite de charger un fichier audio pour un effet aussi léger. Silencieux si
 * le navigateur refuse l'audio (règles d'autoplay tant qu'aucune interaction
 * n'a eu lieu sur la page) : ce n'est qu'un agrément, jamais bloquant.
 */
export function jouerSonNotification(): void {
  try {
    const Ctx = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
    if (!Ctx) return

    const ctx = new Ctx()
    const maintenant = ctx.currentTime

    ;[880, 1108.73].forEach((frequence, i) => {
      const oscillateur = ctx.createOscillator()
      const gain = ctx.createGain()
      const debut = maintenant + i * 0.09

      oscillateur.type = 'sine'
      oscillateur.frequency.setValueAtTime(frequence, debut)
      gain.gain.setValueAtTime(0.0001, debut)
      gain.gain.exponentialRampToValueAtTime(0.15, debut + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, debut + 0.22)

      oscillateur.connect(gain)
      gain.connect(ctx.destination)
      oscillateur.start(debut)
      oscillateur.stop(debut + 0.24)
    })

    setTimeout(() => ctx.close(), 500)
  } catch {
    // L'audio n'est qu'un agrément — un navigateur qui le refuse ne doit rien casser.
  }
}
