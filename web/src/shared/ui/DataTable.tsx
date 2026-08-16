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
}

interface DataTableProps<T> {
  colonnes: Colonne<T>[]
  lignes: T[]
  cleLigne: (ligne: T) => string | number
  /** Barre de recherche : masquée en dessous de ce nombre de lignes. */
  recherche?: boolean
  placeholderRecherche?: string
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
  parPage = 15,
  messageVide,
  onLigneClick,
  outils,
  largeurMin = 640,
}: DataTableProps<T>) {
  const [terme, setTerme] = useState('')
  const [tri, setTri] = useState<{ cle: string; sens: Sens } | null>(null)
  const [page, setPage] = useState(1)

  const texteDe = (ligne: T, colonne: Colonne<T>) => String(colonne.valeur?.(ligne) ?? '').toLowerCase()

  const filtrees = useMemo(() => {
    const q = terme.trim().toLowerCase()
    if (!q) return lignes

    const cherchables = colonnes.filter((c) => c.valeur)
    return lignes.filter((ligne) => cherchables.some((c) => texteDe(ligne, c).includes(q)))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lignes, colonnes, terme])

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
        <table className="w-full border-collapse text-sm" style={{ minWidth: `${largeurMin}px` }}>
          <thead className="bg-linear-to-b from-cream-100 to-cream-100/80 text-left text-xs font-semibold uppercase tracking-wide text-navy-500">
            <tr>
              {colonnes.map((colonne) => {
                const triable = Boolean(colonne.valeur)
                const actif = tri?.cle === colonne.cle

                return (
                  <th
                    key={colonne.cle}
                    className={clsx(
                      'whitespace-nowrap px-4 py-3.5 font-semibold',
                      colonne.masquerMobile && 'hidden sm:table-cell',
                    )}
                  >
                    {triable ? (
                      <button
                        onClick={() => basculerTri(colonne.cle)}
                        className={clsx(
                          'inline-flex items-center gap-1.5 transition-colors hover:text-navy-800',
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
                      colonne.masquerMobile && 'hidden sm:table-cell',
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
