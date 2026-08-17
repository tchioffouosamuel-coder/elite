import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { HeartPulse } from 'lucide-react'
import { fetchVisitesInfirmerie } from '@/features/infirmerie/api'
import { francs } from '@/features/finance/api'
import { Card } from '@/shared/ui/Card'
import { Spinner } from '@/shared/ui/Feedback'

/** Historique de santé d'un élève, pendant infirmier du dossier disciplinaire. */
export function SanteEleveCard({ eleveId }: { eleveId: number }) {
  const { t } = useTranslation()
  const { data: visites, isLoading } = useQuery({
    queryKey: ['infirmerie-visites', { eleve_id: eleveId }],
    queryFn: () => fetchVisitesInfirmerie({ eleve_id: eleveId }),
  })

  const coutTotal = visites?.reduce((somme, v) => somme + (v.cout_soins ?? 0), 0) ?? 0

  return (
    <Card>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <HeartPulse className="h-4 w-4 text-gold-500" />
          {t('eleves.sante.title')}
        </h2>
      </div>

      {isLoading || !visites ? (
        <Spinner />
      ) : visites.length === 0 ? (
        <p className="text-sm text-navy-400">{t('eleves.sante.empty')}</p>
      ) : (
        <>
          <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('eleves.sante.count', { count: visites.length })}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('eleves.sante.cout_total')}</p>
              <p className="text-lg font-bold tabular-nums text-navy-900">{francs(coutTotal)}</p>
            </div>
          </div>

          <div className="flex flex-col divide-y divide-navy-50">
            {visites.map((v) => (
              <div key={v.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                <div className="flex min-w-0 flex-col">
                  <span className="truncate text-sm font-medium text-navy-800">{v.raison}</span>
                  <span className="truncate text-xs text-navy-400">{v.soins_prodiges}</span>
                </div>
                <div className="flex flex-none items-center gap-2">
                  <span className="text-xs text-navy-400">{new Date(v.date_visite).toLocaleDateString('fr-FR')}</span>
                  <span className="text-sm font-semibold tabular-nums text-navy-700">{francs(v.cout_soins)}</span>
                </div>
              </div>
            ))}
          </div>
        </>
      )}
    </Card>
  )
}
