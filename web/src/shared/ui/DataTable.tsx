import { useMemo, useState, type ReactNode } from 'react'
import { ArrowDown, ArrowUp, ChevronsUpDown, Search } from 'lucide-react'
import { clsx } from 'clsx'
import { EmptyState } from '@/shared/ui/Feedback'

export interface Colonne<T> {
  /** Identifiant de colonne, sert de clé de tri. */
  cle: string
  entete: ReactNode
  /** Rendu de la cellule. */
  cellule: (ligne: T) => ReactNode
  /**
   * Valeur servant au tri et à la recherche. Sans elle, la colonne n'est ni
   * triable ni cherchable — utile pour une colonne d'actions.
   */
  valeur?: (ligne: T) => string | number | null | undefined
  className?: string
  /** Masque la colonne en dessous de `sm` pour alléger l'affichage mobile. */
  masquerMobile?: boolean
  /**
   * Fixe la colonne sur le côté indiqué pendant le défilement horizontal —
   * utile pour garder une case à cocher ou des actions toujours visibles sur
   * un tableau large plutôt que de les laisser sortir du cadre.
   */
  sticky?: 'left' | 'right'
  /**
   * Largeur figée (ex. `'220px'`) plutôt que la largeur naturelle du contenu —
   * nécessaire pour qu'un texte long tronque au lieu d'élargir tout le
   * tableau. Dès qu'une colonne la définit, le tableau bascule en layout figé.
   */
  largeur?: string
}

interface DataTableProps<T> {
  colonnes: Colonne<T>[]
  lignes: T[]
  cleLigne: (ligne: T) => string | number
  /** Barre de recherche : masquée en dessous de ce nombre de lignes. */
  recherche?: boolean
  placeholderRecherche?: string
  /**
   * Recherche pilotée par le parent (typiquement une recherche serveur sur
   * tout le jeu de données, pas seulement la page chargée) : quand
   * `onTermeChange` est fourni, le tableau n'applique plus son propre filtre
   * texte sur `lignes` — il fait confiance à l'appelant pour lui avoir déjà
   * passé les bonnes lignes, et se contente d'afficher/piloter le champ.
   */
  terme?: string
  onTermeChange?: (terme: string) => void
  /** Pagination : 0 désactive le découpage. */
  parPage?: number
  messageVide?: string
  onLigneClick?: (ligne: T) => void
  /** Contenu additionnel dans la barre d'outils (filtres propres à la page). */
  outils?: ReactNode
  largeurMin?: number
}

type Sens = 'asc' | 'desc'

/**
 * Tableau de données : recherche plein texte, tri par colonne et pagination,
 * le tout côté client. Convient aux volumes d'une école (quelques centaines de
 * lignes) ; au-delà il faudrait déporter tri et pagination côté API.
 */
export function DataTable<T>({
  colonnes,
  lignes,
  cleLigne,
  recherche = true,
  placeholderRecherche = 'Rechercher…',
  terme: termeControle,
  onTermeChange,
  parPage = 15,
  messageVide,
  onLigneClick,
  outils,
  largeurMin = 640,
}: DataTableProps<T>) {
  const controle = onTermeChange !== undefined
  const [termeInterne, setTermeInterne] = useState('')
  const terme = controle ? (termeControle ?? '') : termeInterne
  const setTerme = controle ? onTermeChange! : setTermeInterne
  const [tri, setTri] = useState<{ cle: string; sens: Sens } | null>(null)
  const [page, setPage] = useState(1)
  const layoutFixe = colonnes.some((c) => c.largeur)

  const texteDe = (ligne: T, colonne: Colonne<T>) => String(colonne.valeur?.(ligne) ?? '').toLowerCase()

  const filtrees = useMemo(() => {
    // Recherche contrôlée : `lignes` vient déjà filtré du serveur, filtrer à
    // nouveau localement ne ferait que masquer des lignes pertinentes que le
    // texte affiché ne contient pas forcément mot pour mot.
    if (controle) return lignes

    const q = terme.trim().toLowerCase()
    if (!q) return lignes

    const cherchables = colonnes.filter((c) => c.valeur)
    return lignes.filter((ligne) => cherchables.some((c) => texteDe(ligne, c).includes(q)))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lignes, colonnes, terme, controle])

  const triees = useMemo(() => {
    if (!tri) return filtrees
    const colonne = colonnes.find((c) => c.cle === tri.cle)
    if (!colonne?.valeur) return filtrees

    const signe = tri.sens === 'asc' ? 1 : -1
    return [...filtrees].sort((a, b) => {
      const va = colonne.valeur!(a)
      const vb = colonne.valeur!(b)

      // Les valeurs absentes tombent en fin de liste quel que soit le sens.
      if (va == null && vb == null) return 0
      if (va == null) return 1
      if (vb == null) return -1

      if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * signe
      return String(va).localeCompare(String(vb), 'fr', { numeric: true }) * signe
    })
  }, [filtrees, tri, colonnes])

  const nbPages = parPage > 0 ? Math.max(1, Math.ceil(triees.length / parPage)) : 1
  const pageCourante = Math.min(page, nbPages)
  const visibles = parPage > 0 ? triees.slice((pageCourante - 1) * parPage, pageCourante * parPage) : triees

  const basculerTri = (cle: string) =>
    setTri((actuel) =>
      actuel?.cle !== cle ? { cle, sens: 'asc' } : actuel.sens === 'asc' ? { cle, sens: 'desc' } : null,
    )

  return (
    <div className="flex flex-col gap-3">
      {(recherche || outils) && (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          {recherche && (
            <div className="relative w-full sm:max-w-xs">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-300" />
              <input
                value={terme}
                onChange={(e) => {
                  setTerme(e.target.value)
                  setPage(1)
                }}
                placeholder={placeholderRecherche}
                className="w-full rounded-xl border border-navy-200 bg-white py-2.5 pl-10 pr-3 text-sm shadow-soft transition-colors placeholder:text-navy-300 focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
              />
            </div>
          )}
          {outils && <div className="flex flex-wrap items-center gap-2">{outils}</div>}
        </div>
      )}

      <div className="overflow-x-auto overscroll-x-contain rounded-2xl border border-navy-100/70 bg-white/75 shadow-card">
        <table
          className="w-full border-collapse text-sm"
          style={{
            minWidth: `${largeurMin}px`,
            tableLayout: layoutFixe ? 'fixed' : undefined,
          }}
        >
          {layoutFixe && (
            <colgroup>
              {colonnes.map((colonne) => (
                <col
                  key={colonne.cle}
                  style={colonne.largeur ? { width: colonne.largeur } : undefined}
                  className={colonne.masquerMobile ? 'hidden sm:table-column' : undefined}
                />
              ))}
            </colgroup>
          )}
          <thead className="bg-linear-to-b from-cream-100 to-cream-100/80 text-left text-xs font-semibold uppercase tracking-wide text-navy-500">
            <tr>
              {colonnes.map((colonne) => {
                const triable = Boolean(colonne.valeur)
                const actif = tri?.cle === colonne.cle

                return (
                  <th
                    key={colonne.cle}
                    className={clsx(
                      'px-4 py-3.5 font-semibold',
                      colonne.largeur ? 'break-words' : 'whitespace-nowrap',
                      // Sous layout figé (dès qu'une colonne pose une largeur), les
                      // colonnes restées sans largeur explicite se voient attribuer une
                      // largeur automatique parfois trop étroite pour leur contenu — sans
                      // ceci son texte déborde par-dessus les colonnes voisines au lieu
                      // d'être simplement tronqué.
                      layoutFixe && 'overflow-hidden',
                      colonne.masquerMobile && 'hidden sm:table-cell',
                      colonne.sticky && 'sticky z-20 bg-cream-100',
                      colonne.sticky === 'left' && 'left-0',
                      colonne.sticky === 'right' && 'right-0',
                    )}
                  >
                    {triable ? (
                      <button
                        onClick={() => basculerTri(colonne.cle)}
                        className={clsx(
                          // Les boutons ne suivent pas naturellement le `uppercase` posé sur
                          // <thead> (feuille de style par défaut des navigateurs pour les
                          // contrôles de formulaire) : sans ce rappel explicite, seules les
                          // colonnes non triables (texte brut) apparaîtraient en majuscules.
                          'inline-flex items-center gap-1.5 uppercase transition-colors hover:text-navy-800',
                          actif && 'text-navy-800',
                        )}
                      >
                        {colonne.entete}
                        {!actif && <ChevronsUpDown className="h-3.5 w-3.5 text-navy-300" />}
                        {actif && tri!.sens === 'asc' && <ArrowUp className="h-3.5 w-3.5 text-gold-600" />}
                        {actif && tri!.sens === 'desc' && <ArrowDown className="h-3.5 w-3.5 text-gold-600" />}
                      </button>
                    ) : (
                      colonne.entete
                    )}
                  </th>
                )
              })}
            </tr>
          </thead>
          <tbody>
            {visibles.map((ligne) => (
              <tr
                key={cleLigne(ligne)}
                onClick={onLigneClick ? () => onLigneClick(ligne) : undefined}
                className={clsx(
                  'border-t border-navy-50 transition-colors even:bg-cream-50/40 hover:bg-gold-50/50',
                  onLigneClick && 'cursor-pointer',
                )}
              >
                {colonnes.map((colonne) => (
                  <td
                    key={colonne.cle}
                    className={clsx(
                      'px-4 py-3.5 align-middle text-navy-800',
                      // `overflow:hidden` sur la cellule court-circuite le
                      // minimum automatique du navigateur (calé sur le
                      // contenu) : sans lui, une colonne figée ne rétrécit
                      // jamais sous la largeur naturelle de son texte. Idem pour
                      // une colonne restée sans largeur explicite dès que la
                      // moindre autre colonne a basculé le tableau en layout figé.
                      layoutFixe && 'overflow-hidden',
                      colonne.masquerMobile && 'hidden sm:table-cell',
                      colonne.sticky && 'sticky z-10 bg-white',
                      colonne.sticky === 'left' && 'left-0',
                      colonne.sticky === 'right' && 'right-0',
                      colonne.className,
                    )}
                  >
                    {colonne.cellule(ligne)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>

        {visibles.length === 0 && (
          <div className="p-4">
            <EmptyState label={terme ? `Aucun résultat pour « ${terme} ».` : messageVide} />
          </div>
        )}
      </div>

      {nbPages > 1 && (
        <div className="flex flex-col items-center justify-between gap-2 text-sm sm:flex-row">
          <span className="text-navy-400">
            {triees.length} résultat{triees.length > 1 ? 's' : ''} · page {pageCourante} sur {nbPages}
          </span>
          <div className="flex gap-1">
            <button
              onClick={() => setPage(pageCourante - 1)}
              disabled={pageCourante === 1}
              className="rounded-lg border border-navy-200 bg-white px-3 py-1.5 text-xs font-semibold text-navy-700 transition-colors hover:bg-cream-50 disabled:opacity-40"
            >
              Précédent
            </button>
            <button
              onClick={() => setPage(pageCourante + 1)}
              disabled={pageCourante === nbPages}
              className="rounded-lg border border-navy-200 bg-white px-3 py-1.5 text-xs font-semibold text-navy-700 transition-colors hover:bg-cream-50 disabled:opacity-40"
            >
              Suivant
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
