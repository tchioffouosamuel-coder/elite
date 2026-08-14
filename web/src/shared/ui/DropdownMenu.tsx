import { useEffect, useRef, useState, type ComponentType } from 'react'
import { createPortal } from 'react-dom'
import { MoreVertical } from 'lucide-react'
import { clsx } from 'clsx'

export interface DropdownMenuItem {
  label: string
  icon?: ComponentType<{ className?: string }>
  onClick: () => void
  danger?: boolean
}

/**
 * Menu "plus d'actions" ancré à un bouton, rendu dans un portail : une cellule
 * de tableau vit dans un conteneur `overflow-x-auto` (DataTable), qui
 * rognerait un menu positionné en `absolute` normal.
 */
export function DropdownMenu({ items, title = 'Plus d’actions' }: { items: DropdownMenuItem[]; title?: string }) {
  const [ouvert, setOuvert] = useState(false)
  const [position, setPosition] = useState<{ top: number; left: number; ouvreVersLeHaut: boolean } | null>(null)
  const declencheurRef = useRef<HTMLButtonElement>(null)
  const menuRef = useRef<HTMLDivElement>(null)

  const positionner = () => {
    const rect = declencheurRef.current?.getBoundingClientRect()
    if (!rect) return
    const espaceBas = window.innerHeight - rect.bottom
    const ouvreVersLeHaut = espaceBas < 200 && rect.top > espaceBas
    setPosition({ top: ouvreVersLeHaut ? rect.top : rect.bottom, left: rect.right, ouvreVersLeHaut })
  }

  const ouvrir = () => {
    positionner()
    setOuvert(true)
  }

  useEffect(() => {
    if (!ouvert) return

    const surClicExterieur = (e: MouseEvent) => {
      const cible = e.target as Node
      if (declencheurRef.current?.contains(cible) || menuRef.current?.contains(cible)) return
      setOuvert(false)
    }
    const surRepositionnement = () => positionner()

    document.addEventListener('mousedown', surClicExterieur)
    window.addEventListener('resize', surRepositionnement)
    window.addEventListener('scroll', surRepositionnement, true)
    return () => {
      document.removeEventListener('mousedown', surClicExterieur)
      window.removeEventListener('resize', surRepositionnement)
      window.removeEventListener('scroll', surRepositionnement, true)
    }
  }, [ouvert])

  return (
    <>
      <button
        ref={declencheurRef}
        type="button"
        title={title}
        onClick={(e) => {
          e.stopPropagation()
          ouvert ? setOuvert(false) : ouvrir()
        }}
        className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
      >
        <MoreVertical className="h-4 w-4" />
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
              ...(position.ouvreVersLeHaut ? { bottom: window.innerHeight - position.top + 4 } : { top: position.top + 4 }),
            }}
            className="z-[100] flex min-w-[220px] flex-col overflow-hidden rounded-xl border border-navy-100 bg-white py-1 shadow-lifted"
          >
            {items.map((item, index) => (
              <button
                key={index}
                type="button"
                onClick={(e) => {
                  e.stopPropagation()
                  setOuvert(false)
                  item.onClick()
                }}
                className={clsx(
                  'flex items-center gap-2.5 px-3.5 py-2.5 text-left text-sm font-medium transition-colors hover:bg-cream-100',
                  item.danger ? 'text-red-600' : 'text-navy-700',
                )}
              >
                {item.icon && <item.icon className="h-4 w-4 flex-none" />}
                {item.label}
              </button>
            ))}
          </div>,
          document.body,
        )}
    </>
  )
}
