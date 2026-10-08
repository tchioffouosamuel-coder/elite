import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { Download, Maximize2, Minus, Plus, Printer, X } from 'lucide-react'
import type { PDFDocumentProxy, RenderTask } from 'pdfjs-dist'
import { useDocumentPreviewStore } from '@/shared/store/documentPreviewStore'
import { chargerPdf, imprimerPdf, type DocumentPdf } from '@/shared/lib/pdf'
import { telechargerBlob } from '@/shared/lib/download'
import { ErrorState, Spinner } from '@/shared/ui/Feedback'

function PagePdf({ pdf, numero, largeur, zoom, onError }: { pdf: PDFDocumentProxy; numero: number; largeur: number; zoom: number; onError: (message: string) => void }) {
  const canvasRef = useRef<HTMLCanvasElement>(null)
  useEffect(() => {
    let annule = false
    let rendu: RenderTask | undefined
    const canvas = canvasRef.current
    if (!canvas || !largeur) return
    delete canvas.dataset.rendered
    pdf.getPage(numero).then(async (page) => {
      if (annule) return
      const format = page.getViewport({ scale: 1 })
      const scale = Math.min(largeur, format.width * 1.5) / format.width * zoom
      const viewport = page.getViewport({ scale })
      const densite = Math.min(window.devicePixelRatio || 1, 2)
      canvas.width = Math.ceil(viewport.width * densite)
      canvas.height = Math.ceil(viewport.height * densite)
      canvas.style.width = `${viewport.width}px`
      canvas.style.height = `${viewport.height}px`
      rendu = page.render({ canvas, viewport, transform: densite === 1 ? undefined : [densite, 0, 0, densite, 0, 0] })
      await rendu.promise
      if (!annule) canvas.dataset.rendered = 'true'
    }).catch((error: Error) => {
      if (!annule) onError(error.message)
    })
    return () => { annule = true; rendu?.cancel() }
  }, [pdf, numero, largeur, zoom, onError])
  return <canvas ref={canvasRef} aria-label={`Page ${numero}`} role="img" className="block shrink-0 bg-white shadow-card" />
}

function ApercuDocument({ document: blob, titre, close }: { document: Blob; titre: string; close: () => void }) {
  const [documentPdf, setDocumentPdf] = useState<DocumentPdf | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [impression, setImpression] = useState(false)
  const [zoom, setZoom] = useState(1)
  const [largeur, setLargeur] = useState(0)
  const contenuRef = useRef<HTMLDivElement>(null)
  const fermerRef = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    let annule = false
    let charge: DocumentPdf | undefined
    setDocumentPdf(null)
    setErreur(null)
    setZoom(1)
    chargerPdf(blob).then((document) => {
      charge = document
      if (annule) void document.destroy()
      else setDocumentPdf(document)
    }).catch((error: Error) => { if (!annule) setErreur(error.message) })
    return () => { annule = true; if (charge) void charge.destroy() }
  }, [blob])

  useEffect(() => {
    const element = contenuRef.current
    if (!element) return
    const observer = new ResizeObserver(() => setLargeur(Math.max(1, element.clientWidth - 32)))
    observer.observe(element)
    return () => observer.disconnect()
  }, [])

  useEffect(() => {
    const precedent = window.document.activeElement as HTMLElement | null
    fermerRef.current?.focus()
    const surTouche = (event: KeyboardEvent) => {
      if (event.key === 'Escape') close()
      if (event.key === 'Tab') {
        const boutons = Array.from(window.document.querySelectorAll<HTMLButtonElement>('[data-document-preview] button:not(:disabled)'))
        const premier = boutons[0]
        const dernier = boutons.at(-1)
        if (event.shiftKey && window.document.activeElement === premier) { event.preventDefault(); dernier?.focus() }
        else if (!event.shiftKey && window.document.activeElement === dernier) { event.preventDefault(); premier?.focus() }
      }
    }
    window.document.addEventListener('keydown', surTouche)
    return () => { window.document.removeEventListener('keydown', surTouche); precedent?.focus() }
  }, [close])

  const imprimer = async () => {
    setImpression(true)
    try { await imprimerPdf(blob) }
    catch (error) { setErreur((error as Error).message) }
    finally { setImpression(false) }
  }
  const boutonOutil = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-navy-700 hover:bg-navy-50 disabled:opacity-40'
  return (
    <div data-document-preview role="dialog" aria-modal="true" aria-label={titre}
      className="fixed inset-x-0 bottom-0 z-[1000] flex min-h-0 flex-col bg-navy-100"
      style={{ top: 'var(--app-top-offset, 0px)', WebkitAppRegion: 'no-drag' } as React.CSSProperties}>
      <header className="relative z-10 flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-navy-200 bg-white px-4 py-3">
        <h2 className="min-w-0 flex-1 truncate font-display text-base font-bold text-navy-900">{titre}</h2>
        <div className="flex max-w-full shrink-0 flex-wrap items-center gap-1">
          <button type="button" className={boutonOutil} title="Zoom arriere" aria-label="Zoom arriere" disabled={!documentPdf || zoom <= 0.5} onClick={() => setZoom((valeur) => Math.max(0.5, valeur - 0.25))}><Minus className="h-4 w-4" /></button>
          <span className="w-12 text-center text-xs tabular-nums text-navy-600">{Math.round(zoom * 100)}%</span>
          <button type="button" className={boutonOutil} title="Zoom avant" aria-label="Zoom avant" disabled={!documentPdf || zoom >= 3} onClick={() => setZoom((valeur) => Math.min(3, valeur + 0.25))}><Plus className="h-4 w-4" /></button>
          <button type="button" className={boutonOutil} title="Ajuster a la largeur" aria-label="Ajuster a la largeur" disabled={!documentPdf} onClick={() => setZoom(1)}><Maximize2 className="h-4 w-4" /></button>
          <button type="button" className={boutonOutil} title="Telecharger le PDF" aria-label="Telecharger le PDF" onClick={() => telechargerBlob(blob, `${titre.replace(/[^a-z0-9-]+/gi, '-') || 'document'}.pdf`)}><Download className="h-4 w-4" /></button>
          <button type="button" onClick={imprimer} disabled={!documentPdf || impression} className="ml-1 flex h-9 items-center gap-2 rounded-lg bg-navy-900 px-3 text-sm font-semibold text-white hover:bg-navy-800 disabled:opacity-50"><Printer className="h-4 w-4" />Imprimer</button>
          <button ref={fermerRef} type="button" onClick={close} title="Fermer l'apercu" aria-label="Fermer l'apercu" className={boutonOutil}><X className="h-5 w-5" /></button>
        </div>
      </header>
      <div ref={contenuRef} className="min-h-0 flex-1 overflow-auto p-4">
        {erreur ? <ErrorState message={erreur} /> : !documentPdf ? <Spinner /> : (
          <div className="flex min-w-full w-fit flex-col items-center gap-4">
            {Array.from({ length: documentPdf.pdf.numPages }, (_, index) => <PagePdf key={index} pdf={documentPdf.pdf} numero={index + 1} largeur={largeur} zoom={zoom} onError={setErreur} />)}
          </div>
        )}
      </div>
    </div>
  )
}

export function DocumentPreviewModal() {
  const { document, titre, close } = useDocumentPreviewStore()
  if (!document) return null
  return createPortal(<ApercuDocument document={document} titre={titre} close={close} />, window.document.body)
}
