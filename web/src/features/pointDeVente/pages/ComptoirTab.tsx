import { useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Barcode, Camera, Minus, Plus, ScanLine, Search, Trash2, Wallet } from 'lucide-react'
import {
  enregistrerVente,
  fetchArticleParCodeBarre,
  fetchCatalogue,
  type ArticleComptoir,
} from '@/features/pointDeVente/api'
import { francs, type ModePaiement } from '@/features/finance/api'
import { fetchEleves } from '@/features/eleves/api'
import { ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { Select as SelectRecherche } from '@/shared/ui/Select'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function ScannerArticleModal({ onCode, onClose }: { onCode: (code: string) => void; onClose: () => void }) {
  const videoRef = useRef<HTMLVideoElement>(null)
  const [code, setCode] = useState('')
  const [erreurCamera, setErreurCamera] = useState<string | null>(null)

  useEffect(() => {
    let arret = false
    let flux: MediaStream | null = null
    let animation = 0

    const demarrer = async () => {
      try {
        flux = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } })
        if (arret || !videoRef.current) return
        videoRef.current.srcObject = flux
        await videoRef.current.play()

        const BarcodeDetector = (window as Window & { BarcodeDetector?: new (options?: { formats: string[] }) => { detect: (source: HTMLVideoElement) => Promise<{ rawValue: string }[]> } }).BarcodeDetector
        if (!BarcodeDetector) {
          setErreurCamera('La détection caméra n’est pas disponible : saisissez le code ci-dessous.')
          return
        }

        const detecteur = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a'] })
        const chercher = async () => {
          if (arret || !videoRef.current) return
          try {
            const resultats = await detecteur.detect(videoRef.current)
            const valeur = resultats[0]?.rawValue?.trim()
            if (valeur) {
              onCode(valeur)
              return
            }
          } catch {
            // La caméra peut ne pas avoir encore produit une image exploitable.
          }
          animation = window.requestAnimationFrame(chercher)
        }
        animation = window.requestAnimationFrame(chercher)
      } catch {
        setErreurCamera('Impossible d’accéder à la caméra : saisissez le code ci-dessous.')
      }
    }

    void demarrer()
    return () => {
      arret = true
      window.cancelAnimationFrame(animation)
      flux?.getTracks().forEach((track) => track.stop())
    }
  }, [onCode])

  const valider = () => {
    const valeur = code.trim()
    if (valeur) onCode(valeur)
  }

  return (
    <Modal title="Scanner le code de l’article" onClose={onClose}>
      <div className="flex flex-col gap-4">
        <div className="overflow-hidden rounded-xl bg-navy-900">
          <video ref={videoRef} className="aspect-video w-full object-cover" muted playsInline />
        </div>
        <p className="text-sm text-navy-500">Placez le code-barres dans le cadre. Le code détecté sera recherché automatiquement dans le catalogue.</p>
        {erreurCamera && <p className="text-sm text-amber-600">{erreurCamera}</p>}
        <div className="flex gap-2">
          <Input
            value={code}
            onChange={(event) => setCode(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault()
                valider()
              }
            }}
            placeholder="Saisir le code de l’article…"
            className="font-mono"
          />
          <Button onClick={valider} disabled={!code.trim()}>Utiliser le code</Button>
        </div>
      </div>
    </Modal>
  )
}

interface LignePanier {
  article: ArticleComptoir
  quantite: number
  prixUnitaire: number
}

/** Un code d'étiquette : treize chiffres, rien d'autre. */
const CODE_BARRE = /^\d{13}$/

/**
 * Comptoir de vente.
 *
 * La saisie est pensée pour une douchette USB, qui se comporte comme un
 * clavier : elle tape le code puis valide. Le champ de scan reprend donc le
 * focus après chaque ajout — sans quoi le deuxième article scanné partirait
 * dans le vide, et le vendeur devrait cliquer entre chaque produit.
 *
 * Le même champ accepte un nom : treize chiffres déclenchent la recherche par
 * code-barres, tout le reste filtre le catalogue. Une seule zone de saisie à
 * apprendre, qu'on ait une douchette ou non.
 */
export function ComptoirTab() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const can = useAuthStore((s) => s.can)
  const peutVendre = can('point_de_vente.vendre')

  const [saisie, setSaisie] = useState('')
  const [recherche, setRecherche] = useState('')
  const [panier, setPanier] = useState<LignePanier[]>([])
  const [mode, setMode] = useState<ModePaiement>('especes')
  const [eleveId, setEleveId] = useState<number | null>(null)
  const [client, setClient] = useState('')
  const [note, setNote] = useState('')
  const [encaissementEnCours, setEncaissementEnCours] = useState(false)
  const [messageScan, setMessageScan] = useState<string | null>(null)
  const [scannerOuvert, setScannerOuvert] = useState(false)
  const champScanRef = useRef<HTMLInputElement>(null)

  const { data: catalogue = [], isLoading } = useQuery({
    queryKey: ['pdv-catalogue'],
    queryFn: () => fetchCatalogue(),
  })
  const { data: eleves } = useQuery({
    queryKey: ['eleves', 'comptoir'],
    queryFn: () => fetchEleves({ per_page: 1000 }),
  })

  // Le catalogue tient en mémoire (quelques dizaines de références) : filtrer
  // ici évite un aller-retour réseau à chaque frappe du vendeur.
  const articlesAffiches = useMemo(() => {
    const terme = recherche.trim().toLowerCase()
    if (terme === '') return catalogue

    return catalogue.filter(
      (article) => article.nom.toLowerCase().includes(terme) || article.code_barre === terme,
    )
  }, [catalogue, recherche])

  const total = panier.reduce((somme, ligne) => somme + ligne.quantite * ligne.prixUnitaire, 0)

  useEffect(() => {
    champScanRef.current?.focus()
  }, [])

  const redonnerLeFocus = () => champScanRef.current?.focus()

  /** Quantité déjà au panier : le stock disponible s'apprécie sur ce qui reste. */
  const dejaAuPanier = (articleId: number) =>
    panier.find((ligne) => ligne.article.id === articleId)?.quantite ?? 0

  const ajouter = (article: ArticleComptoir) => {
    if (article.quantite <= dejaAuPanier(article.id)) {
      erreur(t('pointDeVente.stock_epuise', { nom: article.nom, quantite: article.quantite }))
      return
    }

    setPanier((actuel) => {
      const existante = actuel.find((ligne) => ligne.article.id === article.id)

      if (existante) {
        return actuel.map((ligne) =>
          ligne.article.id === article.id ? { ...ligne, quantite: ligne.quantite + 1 } : ligne,
        )
      }

      return [...actuel, { article, quantite: 1, prixUnitaire: article.prix_vente ?? 0 }]
    })

    setMessageScan(t('pointDeVente.ajoute', { nom: article.nom }))
    redonnerLeFocus()
  }

  const changerQuantite = (articleId: number, delta: number) => {
    setPanier((actuel) =>
      actuel
        .map((ligne) => {
          if (ligne.article.id !== articleId) return ligne

          const suivante = ligne.quantite + delta

          if (suivante > ligne.article.quantite) {
            erreur(t('pointDeVente.stock_epuise', { nom: ligne.article.nom, quantite: ligne.article.quantite }))
            return ligne
          }

          return { ...ligne, quantite: suivante }
        })
        .filter((ligne) => ligne.quantite > 0),
    )
  }

  const changerPrix = (articleId: number, prix: number) => {
    setPanier((actuel) =>
      actuel.map((ligne) => (ligne.article.id === articleId ? { ...ligne, prixUnitaire: prix } : ligne)),
    )
  }

  const retirer = (articleId: number) => {
    setPanier((actuel) => actuel.filter((ligne) => ligne.article.id !== articleId))
    redonnerLeFocus()
  }

  /**
   * Validation du champ de scan. La douchette envoie le code puis « Entrée » :
   * c'est ici que tout se joue, d'où le traitement des deux cas — code connu
   * de l'API, ou terme à chercher dans le catalogue.
   */
  const validerSaisie = async (saisieRecue = saisie, forcerApi = false) => {
    const valeur = saisieRecue.trim()
    if (valeur === '') return

    if (!CODE_BARRE.test(valeur)) {
      setRecherche(valeur)
      setSaisie('')
      return
    }

    // Le catalogue en mémoire répond sans réseau quand l'article y est déjà.
    const local = forcerApi ? undefined : catalogue.find((article) => article.code_barre === valeur)

    if (local) {
      ajouter(local)
      setSaisie('')
      return
    }

    try {
      const article = await fetchArticleParCodeBarre(valeur)

      if (article.prix_vente === null) {
        setMessageScan(t('pointDeVente.sans_prix', { nom: article.nom }))
        setSaisie('')
        return
      }

      ajouter(article)
    } catch {
      setMessageScan(t('pointDeVente.code_inconnu', { code: valeur }))
    } finally {
      setSaisie('')
      redonnerLeFocus()
    }
  }

  const recevoirCodeScanner = (code: string) => {
    setScannerOuvert(false)
    setSaisie(code)
    // Un scan caméra repasse toujours par l'API : le stock et le prix doivent
    // être vérifiés au moment de l'ajout, avant l'encaissement.
    void validerSaisie(code, true)
  }

  const viderPanier = () => {
    setPanier([])
    setEleveId(null)
    setClient('')
    setNote('')
    setMode('especes')
  }

  const encaisser = async () => {
    if (panier.length === 0) return

    setEncaissementEnCours(true)
    try {
      const vente = await enregistrerVente({
        lignes: panier.map((ligne) => ({
          article_id: ligne.article.id,
          quantite: ligne.quantite,
          prix_unitaire: ligne.prixUnitaire,
        })),
        mode,
        eleve_id: eleveId,
        client: client.trim() || null,
        note: note.trim() || null,
      })

      succes(t('pointDeVente.vente_enregistree', { numero: vente.numero_facture }))
      viderPanier()
      queryClient.invalidateQueries({ queryKey: ['pdv-catalogue'] })
      queryClient.invalidateQueries({ queryKey: ['pdv-ventes'] })
      queryClient.invalidateQueries({ queryKey: ['inventaire'] })

      // La facture s'ouvre dans la foulée : c'est le document qu'on remet.
      ouvrirDocument(`/point-de-vente/ventes/${vente.id}/facture`)
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEncaissementEnCours(false)
      redonnerLeFocus()
    }
  }

  const optionsEleves = (eleves?.items ?? []).map((eleve) => ({
    value: eleve.id,
    label: `${eleve.nom_complet}${eleve.classe?.nom ? ` — ${eleve.classe.nom}` : ''}`,
  }))

  return (
    <div className="grid grid-cols-1 gap-5 lg:grid-cols-[3fr_2fr] lg:items-start">
      {/* ------------------------------------------------------- Catalogue */}
      <div className="flex flex-col gap-4">
        <Card>
          <label className="mb-2 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
            <ScanLine className="h-4 w-4 text-gold-500" />
            {t('pointDeVente.scanner_titre')}
          </label>
          <div className="flex gap-2">
            <div className="relative flex-1">
              <Barcode className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-300" />
              <input
                ref={champScanRef}
                value={saisie}
                onChange={(e) => setSaisie(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault()
                    validerSaisie()
                  }
                }}
                placeholder={t('pointDeVente.scanner_placeholder')}
                className="w-full rounded-xl border border-navy-200 bg-white py-2.5 pl-10 pr-3.5 font-mono text-sm text-navy-900 shadow-soft transition-colors placeholder:font-sans placeholder:text-navy-300 focus:border-gold-400 focus:outline-none focus:ring-4 focus:ring-gold-100"
              />
            </div>
            <Button variant="secondary" onClick={validerSaisie}>
              <Search className="h-4 w-4" />
              {t('pointDeVente.chercher')}
            </Button>
            <Button variant="secondary" onClick={() => setScannerOuvert(true)} title="Scanner avec la caméra">
              <Camera className="h-4 w-4" />
              <span className="hidden sm:inline">Scanner</span>
            </Button>
          </div>
          <p className="mt-2 text-xs text-navy-400">{t('pointDeVente.scanner_aide')}</p>
          {messageScan && <p className="mt-1 text-xs font-semibold text-gold-600">{messageScan}</p>}
        </Card>

        <Card>
          <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 className="text-sm font-bold uppercase tracking-wide text-navy-500">
              {t('pointDeVente.catalogue')}
            </h2>
            {recherche && (
              <button
                onClick={() => {
                  setRecherche('')
                  redonnerLeFocus()
                }}
                className="text-xs font-semibold text-navy-500 underline hover:text-navy-700"
              >
                {t('pointDeVente.effacer_filtre', { terme: recherche })}
              </button>
            )}
          </div>

          {isLoading ? (
            <Spinner />
          ) : articlesAffiches.length === 0 ? (
            <EmptyState label={t('pointDeVente.catalogue_vide')} />
          ) : (
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
              {articlesAffiches.map((article) => {
                const restant = article.quantite - dejaAuPanier(article.id)

                return (
                  <button
                    key={article.id}
                    onClick={() => ajouter(article)}
                    disabled={restant <= 0 || !peutVendre}
                    className="flex flex-col items-start gap-1 rounded-xl border border-navy-100 bg-white/70 p-3 text-left transition-colors hover:border-gold-300 hover:bg-gold-50/40 disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:border-navy-100 disabled:hover:bg-white/70"
                  >
                    <span className="text-sm font-semibold text-navy-900">{article.nom}</span>
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="text-sm font-bold tabular-nums text-gold-600">
                        {francs(article.prix_vente ?? 0)}
                      </span>
                      <Badge tone={restant > 5 ? 'green' : restant > 0 ? 'gold' : 'red'}>
                        {t('pointDeVente.en_stock', { count: restant })}
                      </Badge>
                    </span>
                    {article.code_barre && (
                      <span className="font-mono text-[11px] text-navy-300">{article.code_barre}</span>
                    )}
                  </button>
                )
              })}
            </div>
          )}
        </Card>
      </div>

      {scannerOuvert && <ScannerArticleModal onCode={recevoirCodeScanner} onClose={() => setScannerOuvert(false)} />}

      {/* ----------------------------------------------------------- Panier */}
      <Card className="lg:sticky lg:top-4">
        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <Wallet className="h-4 w-4 text-gold-500" />
          {t('pointDeVente.panier')}
        </h2>

        {panier.length === 0 ? (
          <p className="py-6 text-center text-sm text-navy-400">{t('pointDeVente.panier_vide')}</p>
        ) : (
          <div className="flex flex-col divide-y divide-navy-50">
            {panier.map((ligne) => (
              <div key={ligne.article.id} className="flex flex-col gap-2 py-3 first:pt-0">
                <div className="flex items-start justify-between gap-2">
                  <span className="text-sm font-semibold text-navy-800">{ligne.article.nom}</span>
                  <button
                    onClick={() => retirer(ligne.article.id)}
                    title={t('common.delete')}
                    className="rounded-lg p-1 text-navy-300 transition-colors hover:bg-red-50 hover:text-red-600"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  <div className="flex items-center gap-1 rounded-lg border border-navy-200 bg-white">
                    <button
                      onClick={() => changerQuantite(ligne.article.id, -1)}
                      className="px-2 py-1 text-navy-500 transition-colors hover:text-navy-800"
                    >
                      <Minus className="h-3.5 w-3.5" />
                    </button>
                    <span className="min-w-[2ch] text-center text-sm font-bold tabular-nums">{ligne.quantite}</span>
                    <button
                      onClick={() => changerQuantite(ligne.article.id, 1)}
                      className="px-2 py-1 text-navy-500 transition-colors hover:text-navy-800"
                    >
                      <Plus className="h-3.5 w-3.5" />
                    </button>
                  </div>

                  <span className="text-xs text-navy-400">×</span>

                  <input
                    type="number"
                    min={0}
                    value={ligne.prixUnitaire}
                    onChange={(e) => changerPrix(ligne.article.id, Number(e.target.value) || 0)}
                    title={t('pointDeVente.prix_unitaire')}
                    className="w-24 rounded-lg border border-navy-200 px-2 py-1 text-right text-sm tabular-nums focus:border-navy-400 focus:outline-none"
                  />

                  <span className="ml-auto text-sm font-bold tabular-nums text-navy-900">
                    {francs(ligne.quantite * ligne.prixUnitaire)}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}

        <div className="mt-4 flex items-center justify-between border-t border-navy-100 pt-3">
          <span className="text-sm font-bold uppercase tracking-wide text-navy-500">{t('pointDeVente.total')}</span>
          <span className="font-display text-2xl font-bold tabular-nums text-navy-900">{francs(total)}</span>
        </div>

        <div className="mt-4 flex flex-col gap-3">
          <Select label={t('pointDeVente.mode')} value={mode} onChange={(e) => setMode(e.target.value as ModePaiement)}>
            <option value="especes">{t('pointDeVente.modes.especes')}</option>
            <option value="mobile_money">{t('pointDeVente.modes.mobile_money')}</option>
            <option value="virement">{t('pointDeVente.modes.virement')}</option>
            <option value="cheque">{t('pointDeVente.modes.cheque')}</option>
            <option value="depot_bancaire">{t('pointDeVente.modes.depot_bancaire')}</option>
          </Select>

          <div>
            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-navy-400">
              {t('pointDeVente.eleve')}
            </span>
            <SelectRecherche
              options={optionsEleves}
              value={optionsEleves.find((option) => option.value === eleveId) ?? null}
              placeholder={t('pointDeVente.eleve_placeholder')}
              onChange={(option) => setEleveId(option ? Number(option.value) : null)}
              isSearchable
              isClearable
            />
          </div>

          {eleveId === null && (
            <Input
              label={t('pointDeVente.client')}
              value={client}
              onChange={(e) => setClient(e.target.value)}
              placeholder={t('pointDeVente.client_placeholder')}
            />
          )}

          <Textarea label={t('pointDeVente.note')} rows={2} value={note} onChange={(e) => setNote(e.target.value)} />

          <div className="flex gap-2">
            <Button
              className="flex-1"
              disabled={panier.length === 0 || encaissementEnCours || !peutVendre}
              onClick={encaisser}
            >
              <Wallet className="h-4 w-4" />
              {encaissementEnCours ? t('common.loading') : t('pointDeVente.encaisser')}
            </Button>
            {panier.length > 0 && (
              <Button variant="secondary" onClick={viderPanier} disabled={encaissementEnCours}>
                {t('common.cancel')}
              </Button>
            )}
          </div>

          {!peutVendre && <p className="text-xs text-navy-400">{t('pointDeVente.sans_droit_vendre')}</p>}
        </div>
      </Card>
    </div>
  )
}
