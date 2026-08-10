import { type ReactNode } from 'react'
import { X } from 'lucide-react'

export function Modal({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
  return (
    <div
      className="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-navy-900/50 p-4 backdrop-blur-sm"
      onClick={onClose}
    >
      <div
        className="animate-scale-in max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-navy-100/70 bg-white p-6 shadow-lifted"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-5 flex items-center justify-between">
          <h2 className="font-display text-lg font-bold tracking-tight text-navy-900">{title}</h2>
          <button
            onClick={onClose}
            className="rounded-full p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        {children}
      </div>
    </div>
  )
}
