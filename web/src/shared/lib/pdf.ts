import type { PDFDocumentProxy, PDFWorker } from 'pdfjs-dist'

export interface DocumentPdf {
  pdf: PDFDocumentProxy
  destroy: () => Promise<void>
}

type Pdfjs = typeof import('pdfjs-dist/legacy/build/pdf.mjs')

/**
 * pdf.js et son worker pèsent près de deux mégaoctets : les importer au
 * moment du premier reçu ajoutait une demi-seconde devant l'aperçu, et
 * recréer un worker à chaque document en ajoutait encore à chaque fois.
 * Le module est donc chargé une seule fois et le worker reste en vie pour
 * toute la session — un worker inactif ne coûte rien, le redémarrer si.
 */
let modulePdf: Promise<Pdfjs> | undefined
// Le worker embarque son code : aucun chargement distant ou file:// requis.
let moduleWorker: Promise<{ default: new () => Worker }> | undefined
let workerPartage: Promise<PDFWorker> | undefined

/** Les deux modules partent ensemble : ils ne dépendent pas l'un de l'autre. */
function charger(): Promise<Pdfjs> {
  modulePdf ??= import('pdfjs-dist/legacy/build/pdf.mjs')
  moduleWorker ??= import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&inline')

  return modulePdf
}

function chargerWorker(): Promise<{ default: new () => Worker }> {
  charger()

  return moduleWorker!
}

/**
 * C'est la promesse qui est mémorisée, pas le worker : deux documents
 * demandés coup sur coup (le préchargement et l'ouverture d'un reçu, par
 * exemple) doivent attendre le même worker, pas en démarrer deux dont l'un
 * resterait orphelin.
 */
function worker(): Promise<PDFWorker> {
  workerPartage ??= (async () => {
    const [lib, { default: PdfWorker }] = await Promise.all([charger(), chargerWorker()])
    const moteur = lib.PDFWorker.create({ port: new PdfWorker() })
    await moteur.promise

    return moteur
  })().catch((erreur: unknown) => {
    // Un échec ne doit pas condamner la session : la tentative suivante repart.
    workerPartage = undefined
    throw erreur
  })

  return workerPartage
}

/**
 * Prépare pdf.js sans rien afficher. Appelé dès que l'application est au
 * repos, et en parallèle du téléchargement d'un document : quand le PDF
 * arrive, il ne reste plus qu'à le lire.
 */
export function prechargerPdf(): void {
  void worker().catch(() => undefined)
}

export async function chargerPdf(blob: Blob): Promise<DocumentPdf> {
  let moteur = await worker()
  // `destroyed` passe à vrai si le worker a été tué (onglet mis en veille
  // prolongée, erreur fatale) : on en remonte un plutôt que d'échouer.
  if (moteur.destroyed) {
    workerPartage = undefined
    moteur = await worker()
  }
  const lib = await charger()
  const task = lib.getDocument({ data: new Uint8Array(await blob.arrayBuffer()), worker: moteur, useSystemFonts: true })
  // Seule la tâche est détruite : le worker sert au document suivant.
  const destroy = () => task.destroy()
  try { return { pdf: await task.promise, destroy } }
  catch (error) { await destroy(); throw error }
}

/**
 * Imprime un PDF déjà chargé, ou le blob brut. Les appelants qui affichent
 * déjà le document (l'aperçu) passent leur `DocumentPdf` : sans cela, le
 * même fichier était analysé une seconde fois juste pour être imprimé.
 */
export async function imprimerPdf(source: Blob | DocumentPdf): Promise<void> {
  const prete = source instanceof Blob ? await chargerPdf(source) : source
  const aNous = source instanceof Blob
  const iframe = document.createElement('iframe')
  iframe.setAttribute('aria-hidden', 'true')
  iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:1px;height:1px;border:0;'
  document.body.appendChild(iframe)
  let timer: number | undefined
  let nettoye = false
  const urls: string[] = []
  const nettoyer = () => {
    if (nettoye) return
    nettoye = true
    window.clearTimeout(timer)
    iframe.remove()
    urls.forEach((url) => URL.revokeObjectURL(url))
  }
  try {
    const fenetre = iframe.contentWindow
    const doc = iframe.contentDocument
    if (!fenetre || !doc) throw new Error("Fenetre d'impression indisponible.")
    const style = doc.createElement('style')
    const premierePage = await prete.pdf.getPage(1)
    const format = premierePage.getViewport({ scale: 1 })
    style.textContent = `@page { size: ${format.width}pt ${format.height}pt; margin: 0; } html, body { margin: 0; } img { display: block; width: 100%; height: auto; } section { break-after: page; } section:last-child { break-after: auto; }`
    doc.head.appendChild(style)
    for (let numero = 1; numero <= prete.pdf.numPages; numero++) {
      const page = await prete.pdf.getPage(numero)
      const viewport = page.getViewport({ scale: 2 })
      const canvas = doc.createElement('canvas')
      canvas.width = Math.ceil(viewport.width)
      canvas.height = Math.ceil(viewport.height)
      await page.render({ canvas, viewport }).promise
      // Un blob plutôt qu'une data-URL : `toDataURL` encode le PNG puis le
      // réencode en base64, que le navigateur doit ensuite redécoder — pour
      // une image qui ne quitte jamais la page.
      const url = await new Promise<string>((resoudre, rejeter) => {
        canvas.toBlob((image) => {
          if (!image) return rejeter(new Error("Rendu de la page impossible."))
          resoudre(URL.createObjectURL(image))
        }, 'image/png')
      })
      urls.push(url)
      const image = doc.createElement('img')
      image.src = url
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
    if (aNous) await prete.destroy()
  }
}
