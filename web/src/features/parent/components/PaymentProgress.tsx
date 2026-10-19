import { francs } from '@/features/finance/api'

export function PaymentProgress({ paid, total, label = 'Progression des paiements / Payment progress', showLabel = true }: {
  paid: number
  total: number
  label?: string
  showLabel?: boolean
}) {
  const percent = total > 0 ? Math.min(100, Math.max(0, paid / total * 100)) : 100
  const complete = percent >= 100

  return (
    <div className="flex min-w-0 flex-col gap-1.5">
      <div className={`flex flex-wrap items-center gap-x-3 gap-y-1 text-xs ${showLabel ? 'justify-between' : 'justify-end'}`}>
        {showLabel && <span className="font-semibold text-navy-600">{label}</span>}
        <span className={`font-bold tabular-nums ${complete ? 'text-green-700' : 'text-navy-800'}`}>
          {Math.round(percent)} %
        </span>
      </div>
      <div role="progressbar" aria-label={label} aria-valuemin={0} aria-valuemax={100} aria-valuenow={percent}
        aria-valuetext={`${Math.round(percent)} % · ${francs(paid)} / ${francs(total)}`}
        className="h-2.5 overflow-hidden rounded-full bg-navy-100">
        <div className={`h-full rounded-full transition-[width] ${complete ? 'bg-green-500' : 'bg-navy-600'}`}
          style={{ width: `${percent}%` }} />
      </div>
      <p className="text-xs tabular-nums text-navy-500">
        {francs(paid)} versés sur / paid out of {francs(total)}
      </p>
    </div>
  )
}
