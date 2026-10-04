import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { CalendarCheck, UserX, ClipboardCheck } from 'lucide-react'
import clsx from 'clsx'
import { fetchAssiduite, PERIODES_ASSIDUITE, type PeriodeAssiduite } from '@/features/dashboard/api'
import { Card } from '@/shared/ui/Card'

/**
 * Présence relevée à l'appel sur le mois, le trimestre ou l'année en cours —
 * même source que la carte « Vue d'ensemble » de l'app mobile
 * (`GET /dashboard/assiduite`). Bornée par l'API aux classes de l'enseignant
 * quand c'est lui qui consulte.
 */
export function CarteAssiduite() {
  const { t } = useTranslation()
  const [periode, setPeriode] = useState<PeriodeAssiduite>('mois')
  const { data, isLoading, isError } = useQuery({
    queryKey: ['dashboard-assiduite', periode],
    queryFn: () => fetchAssiduite(periode),
    staleTime: 60_000,
  })

  const libelles: Record<PeriodeAssiduite, string> = {
    mois: t('dashboard.attendance.month'),
    trimestre: t('dashboard.attendance.term'),
    annee: t('dashboard.attendance.year'),
  }
  const taux = data?.taux_presence ?? null
  const couleurTaux = taux === null ? 'text-navy-400' : taux >= 90 ? 'text-emerald-600' : taux >= 75 ? 'text-amber-600' : 'text-red-600'
  const valeur = (v: number | string | null | undefined) => (isLoading ? '…' : isError || v === null || v === undefined ? '—' : v)

  return (
    <Card>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 className="font-display text-base font-bold tracking-tight text-navy-800">{t('dashboard.attendance.title')}</h2>
        <div role="group" aria-label={t('dashboard.attendance.period')} className="inline-flex rounded-xl border border-navy-100 p-0.5">
          {PERIODES_ASSIDUITE.map((p) => (
            <button
              key={p}
              type="button"
              aria-pressed={periode === p}
              onClick={() => setPeriode(p)}
              className={clsx(
                'rounded-lg px-3 py-1 text-xs font-semibold transition-colors',
                periode === p ? 'bg-navy-700 text-white' : 'text-navy-500 hover:bg-navy-50',
              )}
            >
              {libelles[p]}
            </button>
          ))}
        </div>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="flex flex-col items-center gap-1 text-center">
          <CalendarCheck className="h-5 w-5 text-emerald-500" aria-hidden />
          <span className={clsx('text-2xl font-extrabold tabular-nums', couleurTaux)}>
            {valeur(taux === null ? null : `${taux.toLocaleString(undefined, { maximumFractionDigits: 1 })} %`)}
          </span>
          <span className="text-xs text-navy-500">{t('dashboard.attendance.rate')}</span>
        </div>
        <div className="flex flex-col items-center gap-1 text-center">
          <UserX className="h-5 w-5 text-red-500" aria-hidden />
          <span className="text-2xl font-extrabold tabular-nums text-navy-800">{valeur(data?.absences)}</span>
          <span className="text-xs text-navy-500">{t('dashboard.attendance.absences')}</span>
        </div>
        <div className="flex flex-col items-center gap-1 text-center">
          <ClipboardCheck className="h-5 w-5 text-navy-400" aria-hidden />
          <span className="text-2xl font-extrabold tabular-nums text-navy-800">{valeur(data?.pointages)}</span>
          <span className="text-xs text-navy-500">{t('dashboard.attendance.checks')}</span>
        </div>
      </div>

      {!isLoading && !isError && data && data.pointages === 0 && (
        <p className="mt-4 text-center text-xs text-navy-400">{t('dashboard.attendance.noCall')}</p>
      )}
    </Card>
  )
}
