import { createPortal } from 'react-dom'
import { Minus, Redo2, Square, Undo2, X } from 'lucide-react'
import logoMark from '@/assets/logo-mark.png'

function TitleBarButton({
  label,
  onClick,
  children,
  danger = false,
}: {
  label: string
  onClick: () => void
  children: React.ReactNode
  danger?: boolean
}) {
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      onMouseDown={(event) => event.preventDefault()}
      onClick={onClick}
      className={`flex h-full w-11 items-center justify-center text-white/60 hover:text-white ${
        danger ? 'hover:bg-red-500' : 'hover:bg-white/10'
      }`}
    >
      {children}
    </button>
  )
}

/**
 * Barre de titre du client desktop : la fenêtre Electron est sans cadre
 * (`frame: false`, cf. `web/desktop/src/main.cjs`), cette barre est donc le
 * seul moyen de la déplacer, la réduire ou la fermer.
 *
 * Rendue une fois pour toute l'application (cf. App.tsx) et non dans
 * AppLayout : sinon elle disparaissait partout ailleurs — page de connexion,
 * écran de veille, changement de mot de passe — laissant l'utilisateur sans
 * aucun moyen de fermer ni réduire la fenêtre. Portail vers `body` avec un
 * z-index maximal, pour rester au-dessus de toute modale ou superposition
 * plein écran (`fixed inset-0`). Le décalage du contenu en dessous est géré
 * par la classe `desktop-shell` (cf. main.tsx et index.css).
 */
export function DesktopTitleBar() {
  if (!window.desktop) return null

  return createPortal(
    <div
      className="fixed inset-x-0 top-0 z-[2147483647] flex h-[var(--desktop-titlebar-height)] select-none items-center border-b border-white/10 bg-[#140d1d] px-3 text-white"
      style={{ WebkitAppRegion: 'drag' } as React.CSSProperties}
      onDoubleClick={() => window.desktop?.toggleMaximizeWindow()}
    >
      <div className="flex min-w-0 items-center gap-2 text-xs font-semibold tracking-wide text-white/80">
        <img src={logoMark} alt="" className="h-5 w-5 rounded-md object-contain" />
        <span className="truncate">Elites School</span>
      </div>
      <div className="ml-auto flex h-full items-center" style={{ WebkitAppRegion: 'no-drag' } as React.CSSProperties}>
        <TitleBarButton label="Annuler (Ctrl+Z)" onClick={() => window.desktop?.undo()}>
          <Undo2 className="h-4 w-4" />
        </TitleBarButton>
        <TitleBarButton label="Rétablir (Ctrl+Y)" onClick={() => window.desktop?.redo()}>
          <Redo2 className="h-4 w-4" />
        </TitleBarButton>
        <span className="mx-1 h-4 w-px bg-white/15" aria-hidden />
        <TitleBarButton label="Réduire" onClick={() => window.desktop?.minimizeWindow()}>
          <Minus className="h-3.5 w-3.5" />
        </TitleBarButton>
        <TitleBarButton label="Agrandir" onClick={() => window.desktop?.toggleMaximizeWindow()}>
          <Square className="h-3 w-3" />
        </TitleBarButton>
        <TitleBarButton label="Fermer" onClick={() => window.desktop?.closeWindow()} danger>
          <X className="h-4 w-4" />
        </TitleBarButton>
      </div>
    </div>,
    document.body,
  )
}
