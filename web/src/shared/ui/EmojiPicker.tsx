import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { SmilePlus } from 'lucide-react'
import { clsx } from 'clsx'

/**
 * Émojis utiles à un référentiel d'appréciations (visages, étoiles, coches…) :
 * un clavier physique ou virtuel n'en propose pas toujours, ce sélecteur
 * permet de choisir sans en taper un au clavier.
 */
const EMOJIS = [
  '😀', '😃', '😄', '😁', '🙂', '😊', '😉', '😌',
  '😐', '😕', '🙁', '☹️', '😢', '😞', '😟', '😣',
  '⭐', '🌟', '✨', '👍', '👎', '👏', '💪', '🎉',
  '✅', '❌', '❓', '❗', '🏆', '🎯', '📈', '📉',
  '❤️', '💚', '💛', '🔵', '🟢', '🟠', '🔴', '⚪',
]

interface EmojiPickerButtonProps {
  onSelect: (emoji: string) => void
  className?: string
}

/**
 * Bouton compact ouvrant un choix d'émojis en pop-over, porté dans `<body>`
 * comme le `Select` recherchable pour ne pas être rogné par une modale.
 */
export function EmojiPickerButton({ onSelect, className }: EmojiPickerButtonProps) {
  const [ouvert, setOuvert] = useState(false)
  const [position, setPosition] = useState<{ top: number; left: number } | null>(null)
  const boutonRef = useRef<HTMLButtonElement>(null)
  const menuRef = useRef<HTMLDivElement>(null)

  const ouvrir = () => {
    const rect = boutonRef.current?.getBoundingClientRect()
    if (rect) setPosition({ top: rect.bottom + 4, left: Math.max(rect.right - 256, 8) })
    setOuvert(true)
  }

  useEffect(() => {
    if (!ouvert) return

    const surClicExterieur = (e: MouseEvent) => {
      const cible = e.target as Node
      if (boutonRef.current?.contains(cible) || menuRef.current?.contains(cible)) return
      setOuvert(false)
    }
    const surEchap = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOuvert(false)
    }

    document.addEventListener('mousedown', surClicExterieur)
    document.addEventListener('keydown', surEchap)
    return () => {
      document.removeEventListener('mousedown', surClicExterieur)
      document.removeEventListener('keydown', surEchap)
    }
  }, [ouvert])

  return (
    <>
      <button
        ref={boutonRef}
        type="button"
        title="Choisir un émoji"
        onClick={() => (ouvert ? setOuvert(false) : ouvrir())}
        className={clsx(
          'flex h-8 w-8 flex-none items-center justify-center rounded-lg text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700',
          className,
        )}
      >
        <SmilePlus className="h-4 w-4" />
      </button>

      {ouvert &&
        position &&
        createPortal(
          <div
            ref={menuRef}
            style={{ position: 'fixed', top: position.top, left: position.left }}
            className="z-[100] grid w-64 grid-cols-8 gap-1 rounded-xl border border-navy-100 bg-white p-2 shadow-lifted"
          >
            {EMOJIS.map((emoji) => (
              <button
                key={emoji}
                type="button"
                onClick={() => {
                  onSelect(emoji)
                  setOuvert(false)
                }}
                className="flex h-7 w-7 items-center justify-center rounded-lg text-lg hover:bg-cream-100"
              >
                {emoji}
              </button>
            ))}
          </div>,
          document.body,
        )}
    </>
  )
}
