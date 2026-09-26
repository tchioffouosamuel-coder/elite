import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Bus, MapPin, Clock, Wallet } from 'lucide-react'
import { fetchElevesTransport, fetchTransportEleve } from '@/features/bus/api'
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
 * La gestion utilise la liste de classe; le mode lecture seule de l'enseignant
 * utilise l'endpoint individuel, borné au périmètre de ses classes.
 */
export function TransportEleveCard({
  eleveId,
  classeId,
  retour,
  lectureSeule = false,
}: {
  eleveId: number
  classeId?: number | null
  retour?: string
  lectureSeule?: boolean
}) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const can = useAuthStore((s) => s.can)

  const { data: eleves, isLoading } = useQuery({
    queryKey: lectureSeule
      ? ['bus-eleve', eleveId]
      : ['bus-eleves', classeId ?? null],
    queryFn: async () => {
      if (lectureSeule) return fetchTransportEleve(eleveId)
      const liste = await fetchElevesTransport(classeId ?? undefined)
      return liste.find((e) => e.id === eleveId) ?? null
    },
  })

  const ligne = eleves
  const bus = ligne?.bus ?? null

  // Une souscription existante se modifie (même écran, prérempli) au lieu
  // d'en créer une seconde, que l'API refuserait (élève déjà affecté).
  const souscrire = () =>
    bus && ligne
      ? navigate('/bus/souscription', {
        state: {
          eleveIds: [eleveId],
          eleveNoms: [ligne.nom_complet],
          affectationId: bus.affectation_id,
          affectationActuelle: {
            trajet_id: bus.trajet.id,
            arret_id: bus.arret?.id ?? null,
            arret_nom: bus.arret?.nom ?? null,
            option_trajet: bus.option_trajet,
            remise: bus.remise,
          },
          retour,
        },
      })
      : navigate(`/bus/souscription/${eleveId}`, retour ? { state: { retour } } : undefined)

  return (
    <Card>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <Bus className="h-4 w-4 text-gold-500" />
          {t('nav.busAffectations')}
        </h2>
        <div className="flex items-center gap-2">
          {bus && <Badge tone={bus.statut_paiement === 'solde' ? 'green' : 'gold'}>{t(`bus.statut_paiement_${bus.statut_paiement}`)}</Badge>}
          {bus && !lectureSeule && (
            <Button size="sm" onClick={() => navigate(`/bus/affectations/${bus.affectation_id}/paiements`)}>
              <Wallet className="h-3.5 w-3.5" />
              {t('bus.paiements')}
            </Button>
          )}
          {!lectureSeule && can('bus.souscrire') && (
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
              {bus.tarif_mensuel != null ? francs(bus.tarif_net) : '—'}
            </p>
            {bus.remise > 0 && bus.tarif_mensuel != null && (
              <p className="text-xs text-navy-400">
                {francs(bus.tarif_mensuel)} − {francs(bus.remise)} ({t('bus.remise_mensuelle').toLowerCase()})
              </p>
            )}
          </div>
        </div>
      )}
    </Card>
  )
}
