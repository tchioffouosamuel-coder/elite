import { clsx } from 'clsx'

type Tone = 'neutral' | 'green' | 'red' | 'gold' | 'blue'

export function Badge({ children, tone = 'neutral' }: { children: React.ReactNode; tone?: Tone }) {
  const toneClasses: Record<Tone, string> = {
    neutral: 'bg-navy-50 text-navy-600 ring-navy-100',
    green: 'bg-green-50 text-green-600 ring-green-100',
    red: 'bg-red-50 text-red-600 ring-red-100',
    gold: 'bg-gold-50 text-gold-600 ring-gold-100',
    blue: 'bg-blue-50 text-blue-600 ring-blue-100',
  }

  return (
    <span
      className={clsx(
        'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
        toneClasses[tone],
      )}
    >
      <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
      {children}
    </span>
  )
}
