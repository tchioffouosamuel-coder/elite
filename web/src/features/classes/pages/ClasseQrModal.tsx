import { useEffect, useState } from 'react'
import QRCode from 'qrcode'
import { Printer } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Spinner } from '@/shared/ui/Feedback'
import type { Classe } from '@/features/classes/api'

/**
 * QR code à afficher au mur de la salle : l'enseignant le scanne avec
 * l'appareil photo de son téléphone en début de cours, ce qui ouvre
 * directement « Ma journée » sur le cours prévu à cette heure dans cette
 * classe — pas d'application dédiée à installer, un lien profond suffit.
 */
export function ClasseQrModal({ classe, onClose }: { classe: Classe; onClose: () => void }) {
  const [image, setImage] = useState<string | null>(null)
  const url = `${window.location.origin}/qr/${classe.qr_token}`

  useEffect(() => {
    let annule = false
    QRCode.toDataURL(url, { width: 320, margin: 1 }).then((data) => {
      if (!annule) setImage(data)
    })
    return () => {
      annule = true
    }
  }, [url])

  const imprimer = () => {
    const fenetre = window.open('', '_blank', 'width=420,height=560')
    if (!fenetre || !image) return

    fenetre.document.write(`
      <!doctype html><html><head><title>QR code — ${classe.nom}</title></head>
      <body style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;gap:16px;">
        <h1 style="font-size:20px;margin:0;">${classe.nom}</h1>
        <img src="${image}" width="320" height="320" />
        <p style="font-size:13px;color:#555;">À scanner en début de cours pour ouvrir l'appel.</p>
      </body></html>
    `)
    fenetre.document.close()
    fenetre.focus()
    fenetre.print()
  }

  return (
    <Modal title={`Code QR — ${classe.nom}`} onClose={onClose}>
      <div className="flex flex-col items-center gap-4">
        <p className="text-center text-sm text-navy-500">
          À afficher au mur de la salle. Scanné par un enseignant en début de cours, il ouvre directement le cours
          prévu à cette heure pour cette classe.
        </p>

        {image ? (
          <img src={image} alt={`QR code de ${classe.nom}`} width={220} height={220} className="rounded-xl border border-navy-100" />
        ) : (
          <div className="flex h-[220px] w-[220px] items-center justify-center">
            <Spinner />
          </div>
        )}

        <Button onClick={imprimer} disabled={!image}>
          <Printer className="h-4 w-4" />
          Imprimer
        </Button>
      </div>
    </Modal>
  )
}
