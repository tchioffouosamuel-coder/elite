import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Wallet } from 'lucide-react'
import { fetchDossier, francs, type StatutPaiement } from '@/features/finance/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Spinner } from '@/shared/ui/Feedback'

const TONS: Record<StatutPaiement, 'red' | 'gold' | 'green' | 'blue' | 'neutral'> = {
  impaye: 'red',
  partiel: 'gold',
  solde: 'green',
  avance: 'blue',
  sans_frais: 'neutral',
}

/** Situation financière d'un élève, pendant financier du dossier disciplinaire. */
export function StatutFinancierCard({ eleveId }: { eleveId: number }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const can = useAuthStore((s) => s.can)
  const { data: dossier, isLoading } = useQuery({
    queryKey: ['dossier-scolarite', eleveId],
    queryFn: () => fetchDossier(eleveId),
  })

  return (
    <Card>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <Wallet className="h-4 w-4 text-gold-500" />
          {t('eleves.financier.title')}
        </h2>
        {dossier && <Badge tone={TONS[dossier.statut_paiement]}>{t(`finance.statuts.${dossier.statut_paiement}`)}</Badge>}
      </div>

      {isLoading || !dossier ? (
        <Spinner />
      ) : (
        <>
          <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('eleves.financier.total_du')}</p>
              <p className="text-lg font-bold tabular-nums text-navy-900">{francs(dossier.total_du)}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('eleves.financier.total_paye')}</p>
              <p className="text-lg font-bold tabular-nums text-navy-900">{francs(dossier.total_paye)}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('eleves.financier.reste_a_payer')}</p>
              <p className="text-lg font-bold tabular-nums text-navy-900">{francs(dossier.reste_a_payer)}</p>
            </div>
            {dossier.avance > 0 && (
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('eleves.financier.avance')}</p>
                <p className="text-lg font-bold tabular-nums text-green-600">{francs(dossier.avance)}</p>
              </div>
            )}
          </div>

          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-navy-400">{t('eleves.financier.versements_recents')}</h3>
          {!dossier.versements || dossier.versements.length === 0 ? (
            <p className="text-sm text-navy-400">{t('eleves.financier.aucun_versement')}</p>
          ) : (
            <div className="flex flex-col divide-y divide-navy-50">
              {dossier.versements
                .filter((v) => !v.annule)
                .slice(0, 5)
                .map((v) => (
                  <div key={v.id} className="flex items-center justify-between gap-2 py-2 first:pt-0 last:pb-0">
                    <span className="font-mono text-xs text-navy-500">{v.numero_recu}</span>
                    <span className="text-xs text-navy-400">{new Date(v.date_versement).toLocaleDateString('fr-FR')}</span>
                    <span className="text-sm font-semibold tabular-nums text-navy-800">{francs(v.montant)}</span>
                  </div>
                ))}
            </div>
          )}

          {can('finance.encaisser') && (
            <div className="mt-4 flex justify-end">
              <Button variant="secondary" size="sm" onClick={() => navigate(`/caisse/encaisser/${eleveId}`)}>
                {t('eleves.financier.encaisser')}
              </Button>
            </div>
          )}
        </>
      )}
    </Card>
  )
}
