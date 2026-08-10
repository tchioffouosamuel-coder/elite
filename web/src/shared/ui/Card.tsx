import { type ComponentType, type ReactNode } from 'react'
import { clsx } from 'clsx'

export function Card({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={clsx(
        'rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card transition-shadow duration-200 sm:p-5',
        className,
      )}
    >
      {children}
    </div>
  )
}

type Accent = 'navy' | 'gold' | 'green' | 'red'

const accentClasses: Record<Accent, { text: string; tile: string; ring: string; bar: string }> = {
  navy: { text: 'text-navy-600', tile: 'bg-linear-to-br from-navy-50 to-navy-100', ring: 'ring-navy-100', bar: 'bg-navy-500' },
  gold: { text: 'text-gold-600', tile: 'bg-linear-to-br from-gold-50 to-gold-100', ring: 'ring-gold-100', bar: 'bg-gold-500' },
  green: { text: 'text-green-600', tile: 'bg-linear-to-br from-green-50 to-green-100', ring: 'ring-green-100', bar: 'bg-green-500' },
  red: { text: 'text-red-600', tile: 'bg-linear-to-br from-red-50 to-red-50', ring: 'ring-red-300/40', bar: 'bg-red-500' },
}

export function StatCard({
  label,
  value,
  accent = 'navy',
  icon: Icon,
  hint,
}: {
  label: string
  value: string | number
  accent?: Accent
  icon?: ComponentType<{ className?: string }>
  hint?: string
}) {
  const tone = accentClasses[accent]

  return (
    <Card className="relative overflow-hidden hover:shadow-lifted">
      {/* Filet coloré : donne à la tuile une identité lisible en un coup d'œil. */}
      <span className={clsx('absolute inset-y-0 left-0 w-1', tone.bar)} />
      <div className="flex items-start justify-between gap-3 pl-2">
        <div className="flex min-w-0 flex-col gap-1">
          {/* Le libellé passe à la ligne plutôt que d'être tronqué : « Enseignants »
              coupé en « ENSEIG… » sur deux colonnes mobiles ne dit plus rien. */}
          <span className="text-xs font-semibold uppercase leading-tight tracking-wide text-navy-400">{label}</span>
          <span className="font-display text-2xl font-bold tracking-tight tabular-nums text-navy-900 sm:text-3xl">
            {value}
          </span>
          {hint && <span className="truncate text-xs text-navy-400">{hint}</span>}
        </div>
        {Icon && (
          <span
            className={clsx(
              'flex h-11 w-11 flex-none items-center justify-center rounded-xl shadow-soft ring-1',
              tone.tile,
              tone.ring,
            )}
          >
            <Icon className={clsx('h-5 w-5', tone.text)} />
          </span>
        )}
      </div>
    </Card>
  )
}
