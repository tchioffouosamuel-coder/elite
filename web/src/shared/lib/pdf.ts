import type { PDFDocumentProxy } from 'pdfjs-dist'

export interface DocumentPdf {
  pdf: PDFDocumentProxy
  destroy: () => Promise<void>
}

export async function chargerPdf(blob: Blob): Promise<DocumentPdf> {
  const [lib, { default: PdfWorker }] = await Promise.all([
    import('pdfjs-dist/legacy/build/pdf.mjs'),
    import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&inline'),
  ])
  // Le worker embarque son code : aucun chargement distant ou file:// requis.
  const port = new PdfWorker()
  const worker = lib.PDFWorker.create({ port })
  const task = lib.getDocument({ data: new Uint8Array(await blob.arrayBuffer()), worker, useSystemFonts: true })
  const destroy = async () => {
    try { await task.destroy() }
    finally { worker.destroy(); port.terminate() }
  }
  try { return { pdf: await task.promise, destroy } }
  catch (error) { await destroy(); throw error }
}

export async function imprimerPdf(blob: Blob): Promise<void> {
  const documentPdf = await chargerPdf(blob)
  const iframe = document.createElement('iframe')
  iframe.setAttribute('aria-hidden', 'true')
  iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:1px;height:1px;border:0;'
  document.body.appendChild(iframe)
  let timer: number | undefined
  let nettoye = false
  const nettoyer = () => {
    if (nettoye) return
    nettoye = true
    window.clearTimeout(timer)
    iframe.remove()
  }
  try {
    const fenetre = iframe.contentWindow
    const doc = iframe.contentDocument
    if (!fenetre || !doc) throw new Error("Fenetre d'impression indisponible.")
    const style = doc.createElement('style')
    const premierePage = await documentPdf.pdf.getPage(1)
    const format = premierePage.getViewport({ scale: 1 })
    style.textContent = `@page { size: ${format.width}pt ${format.height}pt; margin: 0; } html, body { margin: 0; } img { display: block; width: 100%; height: auto; } section { break-after: page; } section:last-child { break-after: auto; }`
    doc.head.appendChild(style)
    for (let numero = 1; numero <= documentPdf.pdf.numPages; numero++) {
      const page = await documentPdf.pdf.getPage(numero)
      const viewport = page.getViewport({ scale: 2 })
      const canvas = doc.createElement('canvas')
      canvas.width = Math.ceil(viewport.width)
      canvas.height = Math.ceil(viewport.height)
      await page.render({ canvas, viewport }).promise
      const image = doc.createElement('img')
      image.src = canvas.toDataURL('image/png')
      const section = doc.createElement('section')
      section.appendChild(image)
      doc.body.appendChild(section)
      await image.decode()
      canvas.width = canvas.height = 0
    }
    // Une iframe HTML de meme origine evite les refus de print() des PDF data:.
    fenetre.addEventListener('afterprint', nettoyer, { once: true })
    fenetre.focus()
    timer = window.setTimeout(nettoyer, 60_000)
    fenetre.print()
  } catch (error) {
    nettoyer()
    throw error
  } finally {
    await documentPdf.destroy()
  }
}
