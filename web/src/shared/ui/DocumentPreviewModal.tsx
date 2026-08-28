import { useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { Printer, X } from 'lucide-react'
import { useDocumentPreviewStore } from '@/shared/store/documentPreviewStore'

/**
 * Aperçu plein écran des documents PDF (reçus, bulletins, attestations…) :
 * monté une seule fois à la racine de l'app, piloté par documentPreviewStore
 * depuis n'importe quelle page via ouvrirDocument(). Le bouton Imprimer
 * ouvre la boîte d'impression du navigateur scopée au contenu de l'iframe,
 * ce qui sert d'aperçu avant impression — l'utilisateur valide ou annule
 * depuis cette boîte plutôt que d'imprimer directement.
 */
export function DocumentPreviewModal() {
  const { url, titre, close } = useDocumentPreviewStore()
  const iframeRef = useRef<HTMLIFrameElement>(null)
  const [charge, setCharge] = useState(false)

  if (!url) return null

  const imprimer = () => {
    iframeRef.current?.contentWindow?.print()
  }

  return createPortal(
    <div
      className="animate-fade-in fixed inset-0 z-50 flex flex-col bg-navy-900/50 backdrop-blur-sm"
      role="presentation"
    >
      <div className="flex flex-none items-center justify-between gap-3 border-b border-navy-100/70 bg-white px-4 py-3 sm:px-6">
        <h2 className="min-w-0 truncate font-display text-base font-bold tracking-tight text-navy-900 sm:text-lg">
          {titre}
        </h2>
        <div className="flex flex-none items-center gap-2">
          <button
            type="button"
            onClick={imprimer}
            disabled={!charge}
            className="flex items-center gap-2 rounded-lg bg-navy-900 px-3 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Printer className="h-4 w-4" />
            Imprimer
          </button>
          <button
            type="button"
            onClick={close}
            aria-label="Fermer"
            className="rounded-full p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
      </div>
      <div className="min-h-0 flex-1 bg-navy-100" onClick={(e) => e.stopPropagation()}>
        <iframe
          ref={iframeRef}
          src={url}
          title={titre}
          className="h-full w-full border-0"
          onLoad={() => setCharge(true)}
        />
      </div>
    </div>,
    document.body,
  )
}
