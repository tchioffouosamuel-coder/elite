import { type ReactNode, type HTMLAttributes } from 'react'
import { clsx } from 'clsx'

export function Table({ children }: { children: ReactNode }) {
  return (
    <div className="overflow-x-auto rounded-2xl border border-navy-100/70 bg-white shadow-card">
      <table className="w-full min-w-[560px] border-collapse text-sm">{children}</table>
    </div>
  )
}

export function Thead({ children }: { children: ReactNode }) {
  return (
    <thead className="bg-cream-100/70 text-left text-xs font-semibold uppercase tracking-wide text-navy-500">
      {children}
    </thead>
  )
}

export function Th({ children }: { children: ReactNode }) {
  return <th className="px-4 py-3.5 font-semibold">{children}</th>
}

export function Td({ children, className }: { children: ReactNode; className?: string }) {
  return <td className={`px-4 py-3.5 text-navy-800 ${className ?? ''}`}>{children}</td>
}

export function Tr({ children, className, ...rest }: HTMLAttributes<HTMLTableRowElement>) {
  return (
    <tr className={clsx('border-t border-navy-50 transition-colors last:border-b-0 hover:bg-cream-50/80', className)} {...rest}>
      {children}
    </tr>
  )
}
