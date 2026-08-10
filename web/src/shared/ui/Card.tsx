import { type ComponentType, type ReactNode } from 'react'
import { clsx } from 'clsx'

export function Card({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={clsx(
        'rounded-2xl border border-navy-100/70 bg-white p-5 shadow-card transition-shadow duration-200',
        className,
      )}
    >
      {children}
    </div>
  )
}

type Accent = 'navy' | 'gold' | 'green' | 'red'

const accentClasses: Record<Accent, { text: string; bg: string; ring: string }> = {
  navy: { text: 'text-navy-600', bg: 'bg-navy-50', ring: 'ring-navy-100' },
  gold: { text: 'text-gold-600', bg: 'bg-gold-50', ring: 'ring-gold-100' },
  green: { text: 'text-green-600', bg: 'bg-green-50', ring: 'ring-green-100' },
  red: { text: 'text-red-600', bg: 'bg-red-50', ring: 'ring-red-100' },
}

export function StatCard({
  label,
  value,
  accent = 'navy',
  icon: Icon,
}: {
  label: string
  value: string | number
  accent?: Accent
  icon?: ComponentType<{ className?: string }>
}) {
  const tone = accentClasses[accent]

  return (
    <Card className="flex items-start justify-between gap-3">
      <div className="flex flex-col gap-1.5">
        <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">{label}</span>
        <span className="font-display text-3xl font-bold tracking-tight tabular-nums text-navy-900">{value}</span>
      </div>
      {Icon && (
        <span className={clsx('flex h-11 w-11 flex-none items-center justify-center rounded-xl ring-1', tone.bg, tone.ring)}>
          <Icon className={clsx('h-5 w-5', tone.text)} />
        </span>
      )}
    </Card>
  )
}
