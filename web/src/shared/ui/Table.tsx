import { type ReactNode, type HTMLAttributes } from 'react'
import { clsx } from 'clsx'

/**
 * Le tableau défile horizontalement dans son propre conteneur : sur mobile la
 * page ne doit jamais déborder, seule la grille de données se fait balayer.
 */
export function Table({ children, minWidth = 560 }: { children: ReactNode; minWidth?: number }) {
  return (
    <div className="overflow-x-auto overscroll-x-contain rounded-2xl border border-navy-100/70 bg-white/75 shadow-card">
      <table className="w-full border-collapse text-sm" style={{ minWidth: `${minWidth}px` }}>
        {children}
      </table>
    </div>
  )
}

export function Thead({ children }: { children: ReactNode }) {
  return (
    <thead className="sticky top-0 z-10 bg-linear-to-b from-cream-100 to-cream-100/80 text-left text-xs font-semibold uppercase tracking-wide text-navy-500 backdrop-blur-sm">
      {children}
    </thead>
  )
}

/** children optionnel : les colonnes d'actions n'ont pas d'intitulé. */
export function Th({ children }: { children?: ReactNode }) {
  return <th className="whitespace-nowrap px-4 py-3.5 font-semibold">{children}</th>
}

export function Td({ children, className }: { children: ReactNode; className?: string }) {
  return <td className={clsx('px-4 py-3.5 align-middle text-navy-800', className)}>{children}</td>
}

export function Tr({ children, className, ...rest }: HTMLAttributes<HTMLTableRowElement>) {
  return (
    <tr
      className={clsx(
        'border-t border-navy-50 transition-colors last:border-b-0 even:bg-cream-50/40 hover:bg-gold-50/50',
        className,
      )}
      {...rest}
    >
      {children}
    </tr>
  )
}
