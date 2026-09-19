import { useEffect, useRef, useState } from 'react'
import { Camera, ImageUp, Loader2, RotateCcw, ZoomIn } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'

const TAILLE_SORTIE = 480

interface Position {
  x: number
  y: number
}

/**
 * Étend un point (x, y) dans la fenêtre de recadrage — bornée pour que
 * l'image affichée couvre toujours entièrement la fenêtre, jamais de vide
 * sur les bords.
 */
function borner(valeur: Position, largeurAffichee: number, hauteurAffichee: number, viewport: number): Position {
  return {
    x: Math.min(0, Math.max(viewport - largeurAffichee, valeur.x)),
    y: Math.min(0, Math.max(viewport - hauteurAffichee, valeur.y)),
  }
}

/**
 * Capture photo (caméra live, avec repli sur un fichier local) puis
 * recadrage carré par glisser/zoomer — pour que le parent puisse composer
 * lui-même une photo d'identité correcte de son enfant, sans repasser par un
 * outil externe.
 */
export function PhotoCaptureModal({
  titre,
  onClose,
  onValider,
}: {
  titre: string
  onClose: () => void
  onValider: (fichier: File) => Promise<void>
}) {
  const [etape, setEtape] = useState<'camera' | 'recadrage'>('camera')
  const [erreurCamera, setErreurCamera] = useState<string | null>(null)
  const [image, setImage] = useState<HTMLImageElement | null>(null)
  const [envoiEnCours, setEnvoiEnCours] = useState(false)

  const videoRef = useRef<HTMLVideoElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const streamRef = useRef<MediaStream | null>(null)

  // Recadrage : zoom (1 = image ajustée pile à la fenêtre) et position de
  // l'image affichée, en pixels CSS, relative au coin haut-gauche de la
  // fenêtre de recadrage.
  const viewportRef = useRef<HTMLDivElement>(null)
  const viewport = 280
  const [zoom, setZoom] = useState(1)
  const [position, setPosition] = useState<Position>({ x: 0, y: 0 })
  const glisserRef = useRef<{ depart: Position; positionDepart: Position } | null>(null)

  const arreterCamera = () => {
    streamRef.current?.getTracks().forEach((t) => t.stop())
    streamRef.current = null
  }

  useEffect(() => {
    if (etape !== 'camera') return

    let annule = false
    setErreurCamera(null)

    navigator.mediaDevices
      ?.getUserMedia({ video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 720 } } })
      .then((stream) => {
        if (annule) {
          stream.getTracks().forEach((t) => t.stop())
          return
        }
        streamRef.current = stream
        if (videoRef.current) videoRef.current.srcObject = stream
      })
      .catch(() => {
        if (!annule) setErreurCamera("Caméra inaccessible — vérifiez l'autorisation, ou importez une photo. / Camera unavailable — check permission, or import a photo instead.")
      })

    return () => {
      annule = true
      arreterCamera()
    }
  }, [etape])

  useEffect(() => () => arreterCamera(), [])

  const chargerImage = (source: string) => {
    const img = new Image()
    img.onload = () => {
      setImage(img)
      setZoom(1)
      setEtape('recadrage')
    }
    img.src = source
  }

  const capturerDepuisCamera = () => {
    const video = videoRef.current
    if (!video || video.videoWidth === 0) return

    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    const ctx = canvas.getContext('2d')
    if (!ctx) return
    ctx.drawImage(video, 0, 0)
    chargerImage(canvas.toDataURL('image/jpeg', 0.92))
  }

  const importerFichier = (e: React.ChangeEvent<HTMLInputElement>) => {
    const fichier = e.target.files?.[0]
    if (!fichier) return
    const lecteur = new FileReader()
    lecteur.onload = () => chargerImage(lecteur.result as string)
    lecteur.readAsDataURL(fichier)
  }

  // Dimensions affichées de l'image dans la fenêtre de recadrage, au zoom
  // courant — l'échelle de base "couvre" toujours la fenêtre (comme
  // `object-fit: cover`), le zoom ne fait qu'agrandir davantage.
  const dimensionsAffichees = () => {
    if (!image) return { largeur: viewport, hauteur: viewport, echelle: 1 }
    const echelleBase = Math.max(viewport / image.naturalWidth, viewport / image.naturalHeight)
    const echelle = echelleBase * zoom
    return { largeur: image.naturalWidth * echelle, hauteur: image.naturalHeight * echelle, echelle }
  }

  useEffect(() => {
    if (etape !== 'recadrage' || !image) return
    const { largeur, hauteur } = dimensionsAffichees()
    setPosition({ x: (viewport - largeur) / 2, y: (viewport - hauteur) / 2 })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [etape, image])

  useEffect(() => {
    if (!image) return
    const { largeur, hauteur } = dimensionsAffichees()
    setPosition((p) => borner(p, largeur, hauteur, viewport))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [zoom])

  const surPointerDown = (e: React.PointerEvent) => {
    e.currentTarget.setPointerCapture(e.pointerId)
    glisserRef.current = { depart: { x: e.clientX, y: e.clientY }, positionDepart: position }
  }

  const surPointerMove = (e: React.PointerEvent) => {
    if (!glisserRef.current || !image) return
    const { depart, positionDepart } = glisserRef.current
    const { largeur, hauteur } = dimensionsAffichees()
    setPosition(
      borner(
        { x: positionDepart.x + (e.clientX - depart.x), y: positionDepart.y + (e.clientY - depart.y) },
        largeur,
        hauteur,
        viewport,
      ),
    )
  }

  const surPointerUp = () => {
    glisserRef.current = null
  }

  const valider = async () => {
    if (!image) return
    const { echelle } = dimensionsAffichees()

    const canvas = document.createElement('canvas')
    canvas.width = TAILLE_SORTIE
    canvas.height = TAILLE_SORTIE
    const ctx = canvas.getContext('2d')
    if (!ctx) return

    const srcX = -position.x / echelle
    const srcY = -position.y / echelle
    const srcTaille = viewport / echelle
    ctx.drawImage(image, srcX, srcY, srcTaille, srcTaille, 0, 0, TAILLE_SORTIE, TAILLE_SORTIE)

    canvas.toBlob(
      async (blob) => {
        if (!blob) return
        const fichier = new File([blob], 'photo.jpg', { type: 'image/jpeg' })
        setEnvoiEnCours(true)
        try {
          await onValider(fichier)
        } finally {
          setEnvoiEnCours(false)
        }
      },
      'image/jpeg',
      0.9,
    )
  }

  const { largeur, hauteur } = dimensionsAffichees()

  return (
    <Modal title={titre} onClose={onClose}>
      <div className="flex flex-col items-center gap-4">
        {etape === 'camera' && (
          <>
            <div className="flex aspect-square w-full max-w-xs items-center justify-center overflow-hidden rounded-2xl bg-navy-900">
              {erreurCamera ? (
                <p className="px-4 text-center text-sm text-cream-100">{erreurCamera}</p>
              ) : (
                <video ref={videoRef} autoPlay playsInline muted className="h-full w-full scale-x-[-1] object-cover" />
              )}
            </div>
            <div className="flex flex-wrap justify-center gap-2">
              {!erreurCamera && (
                <Button onClick={capturerDepuisCamera}>
                  <Camera className="h-4 w-4" />
                  Prendre la photo / Take photo
                </Button>
              )}
              <Button variant="secondary" onClick={() => fileInputRef.current?.click()}>
                <ImageUp className="h-4 w-4" />
                Importer une photo / Import a photo
              </Button>
              <input ref={fileInputRef} type="file" accept="image/jpeg,image/png" className="hidden" onChange={importerFichier} />
            </div>
          </>
        )}

        {etape === 'recadrage' && image && (
          <>
            <div
              ref={viewportRef}
              className="relative aspect-square w-full max-w-xs touch-none select-none overflow-hidden rounded-2xl bg-navy-900"
              style={{ height: viewport }}
              onPointerDown={surPointerDown}
              onPointerMove={surPointerMove}
              onPointerUp={surPointerUp}
              onPointerLeave={surPointerUp}
            >
              <img
                src={image.src}
                alt="Recadrage / Crop"
                draggable={false}
                className="absolute rounded-none"
                style={{ left: position.x, top: position.y, width: largeur, height: hauteur, maxWidth: 'none' }}
              />
              {/* Repère circulaire indicatif : la photo reste carrée, mais l'avatar s'affiche rond. */}
              <div className="pointer-events-none absolute inset-0 rounded-full ring-[100px] ring-navy-900/60" />
            </div>

            <div className="flex w-full max-w-xs items-center gap-2">
              <ZoomIn className="h-4 w-4 flex-none text-navy-400" />
              <input
                type="range"
                min={1}
                max={3}
                step={0.01}
                value={zoom}
                onChange={(e) => setZoom(Number(e.target.value))}
                className="w-full accent-navy-700"
              />
            </div>

            <div className="flex flex-wrap justify-center gap-2">
              <Button variant="secondary" onClick={() => setEtape('camera')} disabled={envoiEnCours}>
                <RotateCcw className="h-4 w-4" />
                Reprendre / Retake
              </Button>
              <Button onClick={() => void valider()} disabled={envoiEnCours}>
                {envoiEnCours ? <Loader2 className="h-4 w-4 animate-spin" /> : <Camera className="h-4 w-4" />}
                {envoiEnCours ? 'Envoi… / Sending…' : 'Valider / Confirm'}
              </Button>
            </div>
          </>
        )}
      </div>
    </Modal>
  )
}
