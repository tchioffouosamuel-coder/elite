import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import QrScanner from 'qr-scanner'
import { ScanLine, AlertTriangle, CameraOff } from 'lucide-react'
import { resoudreQr } from '@/features/progression/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { Spinner } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

/** Extrait le token du lien encodé dans le QR (`.../qr/<token>`), quel que soit l'hôte scanné. */
function extraireToken(texteDecode: string): string | null {
  try {
    const chemin = new URL(texteDecode).pathname
    const correspondance = chemin.match(/\/qr\/([^/]+)/)
    return correspondance?.[1] ?? null
  } catch {
    return null
  }
}

/**
 * Scanner de code QR intégré à l'application : tous les téléphones n'ont pas
 * un appareil photo qui décode nativement les QR codes, donc on ne peut pas
 * compter uniquement sur l'appareil photo natif pour ouvrir le lien de la
 * salle. Cette page ouvre la caméra depuis le navigateur et décode elle-même
 * le code affiché au mur, puis résout et bascule sur « Ma journée » comme si
 * le lien avait été suivi normalement.
 */
export function QrScannerPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const videoRef = useRef<HTMLVideoElement>(null)
  const scannerRef = useRef<QrScanner | null>(null)
  // Le callback de décodage est capturé une fois par QrScanner (effet à deps
  // vides) : un state serait figé dans cette fermeture, une ref reste à jour.
  const resolutionEnCoursRef = useRef(false)

  const [etat, setEtat] = useState<'demarrage' | 'scan' | 'resolution' | 'erreur' | 'pas_de_camera'>('demarrage')
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (!videoRef.current) return

    let annule = false

    QrScanner.hasCamera().then((disponible) => {
      if (annule) return

      if (!disponible) {
        setEtat('pas_de_camera')
        return
      }

      const scanner = new QrScanner(
        videoRef.current!,
        (resultat) => traiterDecodage(resultat.data),
        {
          highlightScanRegion: true,
          highlightCodeOutline: true,
          preferredCamera: 'environment',
        },
      )
      scannerRef.current = scanner

      scanner
        .start()
        .then(() => {
          if (!annule) setEtat('scan')
        })
        .catch(() => {
          if (!annule) {
            setEtat('erreur')
            setErreur("Impossible d'accéder à la caméra. Vérifiez l'autorisation dans les réglages du navigateur.")
          }
        })
    })

    return () => {
      annule = true
      scannerRef.current?.destroy()
      scannerRef.current = null
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const traiterDecodage = async (texteDecode: string) => {
    // Un seul décodage à la fois : sans ça, chaque frame suivante relancerait
    // une résolution tant que le QR reste dans le cadre.
    if (resolutionEnCoursRef.current) return

    const token = extraireToken(texteDecode)
    if (!token) return

    resolutionEnCoursRef.current = true
    scannerRef.current?.stop()
    setEtat('resolution')
    setErreur(null)

    try {
      const resolu = await resoudreQr(token)
      navigate('/ma-journee', { replace: true, state: { classeMatiereId: resolu.classe_matiere_id } })
    } catch (e) {
      setErreur((e as ApiError).message)
      setEtat('erreur')
    } finally {
      resolutionEnCoursRef.current = false
    }
  }

  const relancer = () => {
    setErreur(null)
    setEtat('scan')
    scannerRef.current?.start().catch(() => {
      setEtat('erreur')
      setErreur("Impossible d'accéder à la caméra. Vérifiez l'autorisation dans les réglages du navigateur.")
    })
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('journee.scanner_title')} sousTitre={t('journee.scanner_hint')} icon={ScanLine} />

      <div className="mx-auto flex w-full max-w-md flex-col items-center gap-4">
        {etat === 'pas_de_camera' ? (
          <div className="flex flex-col items-center gap-3 py-10 text-center">
            <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-navy-50 text-navy-400">
              <CameraOff className="h-6 w-6" />
            </span>
            <p className="max-w-sm text-sm font-medium text-navy-700">
              Aucune caméra n'a été détectée sur cet appareil.
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

            {etat === 'resolution' && (
              <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-navy-900/80">
                <Spinner />
                <p className="text-sm font-medium text-cream-50">Ouverture du cours…</p>
              </div>
            )}

            {etat === 'erreur' && (
              <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-navy-900/90 px-6 text-center">
                <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-500">
                  <AlertTriangle className="h-6 w-6" />
                </span>
                <p className="text-sm font-medium text-cream-50">{erreur}</p>
                <Button variant="secondary" onClick={relancer}>
                  Réessayer
                </Button>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
