import { type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { X } from 'lucide-react'

export function Modal({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
  return createPortal(
    <div
      className="animate-fade-in fixed inset-0 z-50 flex items-end justify-center bg-navy-900/50 backdrop-blur-sm sm:items-center sm:p-4"
      role="presentation"
    >
      {/* Feuille ancrée en bas sur mobile, boîte centrée à partir de sm. */}
      <div
        className="animate-scale-in flex max-h-[92svh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border border-navy-100/70 bg-white shadow-lifted sm:max-h-[90vh] sm:rounded-2xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex flex-none items-center justify-between gap-3 border-b border-navy-50 px-5 py-4 sm:px-6">
          <h2 className="min-w-0 truncate font-display text-base font-bold tracking-tight text-navy-900 sm:text-lg">
            {title}
          </h2>
          <button
            type="button"
            onClick={onClose}
            aria-label="Fermer"
            className="flex-none rounded-full p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="overflow-y-auto px-5 py-5 sm:px-6">{children}</div>
      </div>
    </div>,
    document.body,
  )
}
