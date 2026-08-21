import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Bus, MapPin, Clock } from 'lucide-react'
import { fetchElevesTransport } from '@/features/bus/api'
import { francs } from '@/features/finance/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Spinner } from '@/shared/ui/Feedback'

/**
 * Souscription au transport d'un élève, vue depuis sa fiche : jusqu'ici il
 * fallait passer par « Transport › Élèves » et retrouver l'élève dans la
 * liste de l'établissement pour savoir s'il prend le bus.
 *
 * La liste est demandée classe par classe — l'API n'expose pas de lecture par
 * élève, et charger l'effectif entier pour une seule ligne serait coûteux.
 */
export function TransportEleveCard({
  eleveId,
  classeId,
  retour,
}: {
  eleveId: number
  classeId?: number | null
  retour?: string
}) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const can = useAuthStore((s) => s.can)

  const { data: eleves, isLoading } = useQuery({
    queryKey: ['bus-eleves', classeId ?? null],
    queryFn: () => fetchElevesTransport(classeId ?? undefined),
  })

  const ligne = eleves?.find((e) => e.id === eleveId)
  const bus = ligne?.bus ?? null

  const souscrire = () =>
    navigate(`/bus/souscription/${eleveId}`, retour ? { state: { retour } } : undefined)

  return (
    <Card>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <Bus className="h-4 w-4 text-gold-500" />
          {t('nav.busAffectations')}
        </h2>
        <div className="flex items-center gap-2">
          {bus && <Badge tone={bus.statut_paiement === 'solde' ? 'green' : 'gold'}>{t(`bus.statut_paiement_${bus.statut_paiement}`)}</Badge>}
          {can('bus.manage') && (
            <Button size="sm" variant="secondary" onClick={souscrire}>
              <Bus className="h-3.5 w-3.5" />
              {bus ? t('common.edit') : t('bus.souscrire')}
            </Button>
          )}
        </div>
      </div>

      {isLoading ? (
        <Spinner />
      ) : !bus ? (
        <p className="text-sm text-navy-400">{t('bus.aucune_souscription')}</p>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('bus.trajet_select')}</p>
            <p className="text-sm font-semibold text-navy-800">{bus.trajet.nom}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('bus.arret_select')}</p>
            <p className="flex items-center gap-1 text-sm font-semibold text-navy-800">
              <MapPin className="h-3.5 w-3.5 text-navy-300" />
              {bus.arret?.nom ?? '—'}
            </p>
            {bus.arret?.heure_passage && (
              <p className="flex items-center gap-1 text-xs text-navy-400">
                <Clock className="h-3 w-3" />
                {bus.arret.heure_passage}
              </p>
            )}
          </div>
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('bus.option_trajet')}</p>
            <p className="text-sm font-semibold text-navy-800">{t(`bus.${bus.option_trajet}`)}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('bus.tarif_mensuel')}</p>
            <p className="text-lg font-bold tabular-nums text-navy-900">
              {bus.tarif_mensuel != null ? francs(bus.tarif_mensuel) : '—'}
            </p>
          </div>
        </div>
      )}
    </Card>
  )
}
