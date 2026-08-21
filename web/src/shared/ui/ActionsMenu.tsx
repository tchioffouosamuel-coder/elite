import { useEffect, useRef, useState, type ComponentType } from 'react'
import { createPortal } from 'react-dom'
import { ChevronDown, MoreHorizontal } from 'lucide-react'
import { clsx } from 'clsx'

export interface ActionItem {
  label: string
  icon?: ComponentType<{ className?: string }>
  onClick: () => void
  danger?: boolean
  /** Précision affichée sous le libellé : à quoi sert l'action, en une ligne. */
  aide?: string
  disabled?: boolean
}

/**
 * Une section du menu. Les entrées `false`/`null` sont tolérées pour que
 * l'appelant écrive `can('x') && { ... }` sans filtrer lui-même, et une
 * section dont il ne reste rien après filtrage disparaît entièrement (titre
 * compris) plutôt que de laisser un intitulé orphelin.
 */
export interface ActionGroup {
  titre?: string
  items: Array<ActionItem | false | null | undefined>
}

function filtrerGroupes(groupes: ActionGroup[]): Array<{ titre?: string; items: ActionItem[] }> {
  return groupes
    .map((groupe) => ({ titre: groupe.titre, items: groupe.items.filter(Boolean) as ActionItem[] }))
    .filter((groupe) => groupe.items.length > 0)
}

/**
 * Menu d'actions d'une entité : un seul point d'entrée qui rassemble tout ce
 * qu'on peut faire sur l'élève, l'agent ou la classe affichée, quel que soit
 * le module d'origine de l'opération (finance, infirmerie, transport…).
 *
 * Contrairement à `DropdownMenu` — pensé pour une cellule de tableau, avec son
 * déclencheur à trois points — celui-ci porte un libellé explicite et range
 * ses entrées par section : au-delà d'une dizaine d'actions, une liste plate
 * oblige à tout relire pour retrouver « Encaisser un versement ».
 *
 * Rendu dans un portail : l'en-tête d'une fiche peut vivre dans un conteneur
 * à débordement masqué, qui rognerait un menu positionné en `absolute`.
 */
export function ActionsMenu({
  groupes,
  label,
  variant = 'primary',
  className,
}: {
  groupes: ActionGroup[]
  label: string
  variant?: 'primary' | 'secondary'
  className?: string
}) {
  const [ouvert, setOuvert] = useState(false)
  const [position, setPosition] = useState<{ top: number; left: number; ouvreVersLeHaut: boolean } | null>(null)
  const declencheurRef = useRef<HTMLButtonElement>(null)
  const menuRef = useRef<HTMLDivElement>(null)

  const sections = filtrerGroupes(groupes)

  const positionner = () => {
    const rect = declencheurRef.current?.getBoundingClientRect()
    if (!rect) return
    const espaceBas = window.innerHeight - rect.bottom
    const ouvreVersLeHaut = espaceBas < 280 && rect.top > espaceBas
    setPosition({ top: ouvreVersLeHaut ? rect.top : rect.bottom, left: rect.right, ouvreVersLeHaut })
  }

  useEffect(() => {
    if (!ouvert) return

    const surClicExterieur = (e: MouseEvent) => {
      const cible = e.target as Node
      if (declencheurRef.current?.contains(cible) || menuRef.current?.contains(cible)) return
      setOuvert(false)
    }
    const surEchap = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOuvert(false)
    }
    const surRepositionnement = () => positionner()

    document.addEventListener('mousedown', surClicExterieur)
    document.addEventListener('keydown', surEchap)
    window.addEventListener('resize', surRepositionnement)
    window.addEventListener('scroll', surRepositionnement, true)
    return () => {
      document.removeEventListener('mousedown', surClicExterieur)
      document.removeEventListener('keydown', surEchap)
      window.removeEventListener('resize', surRepositionnement)
      window.removeEventListener('scroll', surRepositionnement, true)
    }
  }, [ouvert])

  if (sections.length === 0) return null

  return (
    <>
      <button
        ref={declencheurRef}
        type="button"
        onClick={() => {
          if (ouvert) {
            setOuvert(false)
            return
          }
          positionner()
          setOuvert(true)
        }}
        className={clsx(
          'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold tracking-tight transition-all duration-150 focus-visible:outline-none focus-visible:ring-4 active:scale-[0.97]',
          variant === 'primary'
            ? 'bg-linear-to-b from-navy-700 to-navy-800 text-cream-50 shadow-card hover:from-navy-600 hover:to-navy-700 hover:shadow-lifted focus-visible:ring-navy-200'
            : 'border border-navy-200 bg-white text-navy-700 shadow-soft hover:border-navy-300 hover:bg-cream-50 hover:shadow-card focus-visible:ring-navy-100',
          className,
        )}
      >
        <MoreHorizontal className="h-4 w-4" />
        {label}
        <ChevronDown className={clsx('h-4 w-4 transition-transform', ouvert && 'rotate-180')} />
      </button>

      {ouvert &&
        position &&
        createPortal(
          <div
            ref={menuRef}
            style={{
              position: 'fixed',
              left: position.left,
              transform: 'translateX(-100%)',
              ...(position.ouvreVersLeHaut
                ? { bottom: window.innerHeight - position.top + 6 }
                : { top: position.top + 6 }),
            }}
            className="z-[100] flex max-h-[70vh] min-w-[268px] max-w-[320px] flex-col overflow-y-auto overscroll-contain rounded-xl border border-navy-100 bg-white py-1.5 shadow-lifted"
          >
            {sections.map((section, indexSection) => (
              <div key={indexSection} className="flex flex-col">
                {indexSection > 0 && <span className="my-1 h-px bg-navy-50" />}
                {section.titre && (
                  <span className="px-3.5 pb-1 pt-1.5 text-[11px] font-bold uppercase tracking-wide text-navy-300">
                    {section.titre}
                  </span>
                )}
                {section.items.map((item, index) => (
                  <button
                    key={index}
                    type="button"
                    disabled={item.disabled}
                    onClick={() => {
                      setOuvert(false)
                      item.onClick()
                    }}
                    className={clsx(
                      'flex items-start gap-2.5 px-3.5 py-2 text-left text-sm font-medium transition-colors hover:bg-cream-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent',
                      item.danger ? 'text-red-600' : 'text-navy-700',
                    )}
                  >
                    {item.icon && <item.icon className="mt-0.5 h-4 w-4 flex-none" />}
                    <span className="flex min-w-0 flex-col">
                      <span>{item.label}</span>
                      {item.aide && <span className="text-xs font-normal text-navy-400">{item.aide}</span>}
                    </span>
                  </button>
                ))}
              </div>
            ))}
          </div>,
          document.body,
        )}
    </>
  )
}
