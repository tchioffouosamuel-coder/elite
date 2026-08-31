import { useEffect, useRef, useState } from 'react'
import { AlertTriangle, CameraOff } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Spinner } from '@/shared/ui/Feedback'

/**
 * L'API BarcodeDetector (EAN-13, EAN-8, UPC…) n'a pas de typage officiel dans
 * lib.dom : on ne déclare que ce dont ce composant se sert.
 */
interface DetecteurCodeBarre {
  detect(source: CanvasImageSource): Promise<Array<{ rawValue: string }>>
}

interface DetecteurCodeBarreConstructeur {
  new (options: { formats: string[] }): DetecteurCodeBarre
}

function detecteurDisponible(): DetecteurCodeBarreConstructeur | null {
  const ctor = (window as unknown as { BarcodeDetector?: DetecteurCodeBarreConstructeur }).BarcodeDetector
  return ctor ?? null
}

/**
 * Scanne un code-barres (EAN-13 en priorité) via la caméra du poste et
 * renvoie la valeur détectée. S'appuie sur l'API native `BarcodeDetector` —
 * disponible sur Chrome/Edge/la plupart des Android, pas sur Firefox ni
 * Safari — d'où le message de repli plutôt qu'un blocage silencieux.
 */
export function BarcodeScannerModal({ onDetected, onClose }: { onDetected: (code: string) => void; onClose: () => void }) {
  const videoRef = useRef<HTMLVideoElement>(null)
  const [etat, setEtat] = useState<'demarrage' | 'scan' | 'erreur' | 'non_supporte'>('demarrage')
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    const Detecteur = detecteurDisponible()
    if (!Detecteur) {
      setEtat('non_supporte')
      return
    }

    let annule = false
    let flux: MediaStream | null = null
    let cadre = 0
    const detecteur = new Detecteur({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128'] })

    const boucle = async () => {
      if (annule || !videoRef.current) return

      try {
        const resultats = await detecteur.detect(videoRef.current)
        if (resultats.length > 0 && !annule) {
          onDetected(resultats[0].rawValue)
          return
        }
      } catch {
        // Une image de transition (flou, changement de format) ne vaut pas
        // d'interrompre le scan : on retente à la frame suivante.
      }

      cadre = requestAnimationFrame(boucle)
    }

    navigator.mediaDevices
      .getUserMedia({ video: { facingMode: 'environment' } })
      .then((stream) => {
        if (annule) {
          stream.getTracks().forEach((piste) => piste.stop())
          return
        }
        flux = stream
        if (videoRef.current) {
          videoRef.current.srcObject = stream
          videoRef.current.play().catch(() => {})
        }
        setEtat('scan')
        cadre = requestAnimationFrame(boucle)
      })
      .catch(() => {
        if (!annule) {
          setEtat('erreur')
          setErreur("Impossible d'accéder à la caméra. Vérifiez l'autorisation dans les réglages du navigateur.")
        }
      })

    return () => {
      annule = true
      cancelAnimationFrame(cadre)
      flux?.getTracks().forEach((piste) => piste.stop())
    }
  }, [onDetected])

  return (
    <Modal title="Scanner un code-barres" onClose={onClose}>
      <div className="mx-auto flex w-full max-w-md flex-col items-center gap-4">
        {etat === 'non_supporte' ? (
          <div className="flex flex-col items-center gap-3 py-6 text-center">
            <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-navy-50 text-navy-400">
              <CameraOff className="h-6 w-6" />
            </span>
            <p className="max-w-sm text-sm font-medium text-navy-700">
              Ce navigateur ne prend pas en charge le scan de code-barres. Saisissez le code manuellement, ou ouvrez cette
              page depuis Chrome ou Edge.
            </p>
          </div>
        ) : (
          <div className="relative aspect-square w-full overflow-hidden rounded-2xl bg-navy-900 shadow-card">
            {/* eslint-disable-next-line jsx-a11y/media-has-caption */}
            <video ref={videoRef} className="h-full w-full object-cover" muted playsInline />

            {etat === 'demarrage' && (
              <div className="absolute inset-0 flex items-center justify-center bg-navy-900/70">
                <Spinner />
              </div>
            )}

            {etat === 'scan' && (
              <div className="pointer-events-none absolute inset-x-8 top-1/2 h-16 -translate-y-1/2 rounded-lg border-2 border-gold-400/80" />
            )}

            {etat === 'erreur' && (
              <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-navy-900/90 px-6 text-center">
                <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-500">
                  <AlertTriangle className="h-6 w-6" />
                </span>
                <p className="text-sm font-medium text-cream-50">{erreur}</p>
              </div>
            )}
          </div>
        )}
        <p className="text-center text-xs text-navy-400">Cadrez le code-barres EAN-13 de l'article dans le repère.</p>
      </div>
    </Modal>
  )
}
