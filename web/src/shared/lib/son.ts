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

/**
 * Un ou deux bips brefs à la fréquence donnée — le repère sonore d'un scan au
 * comptoir. Même principe que {@see jouerSonNotification} : généré à la
 * volée, silencieux si l'audio est indisponible, jamais bloquant.
 */
function bips(frequence: number, dureeMs: number, nombre: number): void {
  try {
    const Ctx = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
    if (!Ctx) return

    const ctx = new Ctx()
    const maintenant = ctx.currentTime
    const duree = dureeMs / 1000

    for (let i = 0; i < nombre; i++) {
      const oscillateur = ctx.createOscillator()
      const gain = ctx.createGain()
      const debut = maintenant + i * (duree + 0.06)

      oscillateur.type = 'sine'
      oscillateur.frequency.setValueAtTime(frequence, debut)
      gain.gain.setValueAtTime(0.0001, debut)
      gain.gain.exponentialRampToValueAtTime(0.2, debut + 0.01)
      gain.gain.exponentialRampToValueAtTime(0.0001, debut + duree)

      oscillateur.connect(gain)
      gain.connect(ctx.destination)
      oscillateur.start(debut)
      oscillateur.stop(debut + duree + 0.02)
    }

    setTimeout(() => ctx.close(), nombre * (dureeMs + 60) + 200)
  } catch {
    // L'audio n'est qu'un agrément — un navigateur qui le refuse ne doit rien casser.
  }
}

/** Scan réussi au comptoir : un bip aigu unique, le repère standard des douchettes de caisse. */
export function jouerBipScan(): void {
  bips(1800, 90, 1)
}

/** Scan en échec au comptoir (code inconnu, sans prix, rupture de stock) : deux bips graves. */
export function jouerBipErreur(): void {
  bips(320, 110, 2)
}
