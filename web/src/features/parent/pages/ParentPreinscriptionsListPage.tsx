import { useQuery } from '@tanstack/react-query'
import { ClipboardList } from 'lucide-react'
import { fetchMesPreinscriptions } from '@/features/parent/api'
import { francs } from '@/features/finance/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'

const STATUT_TONE = { en_attente: 'gold', validee: 'green', rejetee: 'red' } as const
const STATUT_LABEL = { en_attente: 'En attente', validee: 'Validée', rejetee: 'Rejetée' } as const

export function ParentPreinscriptionsListPage() {
  const { data, isLoading, isError } = useQuery({ queryKey: ['parent-preinscriptions'], queryFn: fetchMesPreinscriptions })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre="Mes démarches" sousTitre="Historique de vos préinscriptions et leur statut." icon={ClipboardList} />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState label="Aucune démarche pour l'instant." />
      ) : (
        <div className="flex flex-col gap-3">
          {data.map((p) => (
            <Card key={p.id}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-display text-base font-bold text-navy-900">{p.eleve?.nom_complet || p.nom_propose}</p>
                  <p className="mt-0.5 text-xs text-navy-400">
                    {p.type === 'nouveau' ? 'Nouvelle inscription' : 'Révision de fiche'} · Déposée le{' '}
                    {new Date(p.created_at).toLocaleDateString('fr-FR')}
                  </p>
                  {p.montant_verser ? <p className="mt-1 text-xs text-navy-500">Versement proposé : {francs(p.montant_verser)}</p> : null}
                  {p.statut === 'rejetee' && p.motif_rejet && (
                    <p className="mt-2 rounded-lg bg-red-50 px-2.5 py-1.5 text-xs text-red-700">{p.motif_rejet}</p>
                  )}
                </div>
                <Badge tone={STATUT_TONE[p.statut]}>{STATUT_LABEL[p.statut]}</Badge>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
