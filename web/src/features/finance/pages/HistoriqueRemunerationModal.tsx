import { useQuery } from '@tanstack/react-query'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { Badge } from '@/shared/ui/Badge'
import { fetchHistoriqueRemunerations, francs, type LigneRemuneration } from '@/features/finance/api'

/**
 * Historique des rémunérations d'un agent.
 *
 * Justifie une augmentation en montrant ce qui la précédait, et explique
 * pourquoi un bulletin ancien n'affiche pas le salaire actuel.
 */
export function HistoriqueRemunerationModal({
  personnel,
  onClose,
}: {
  personnel: LigneRemuneration
  onClose: () => void
}) {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['historique-remuneration', personnel.id],
    queryFn: () => fetchHistoriqueRemunerations(personnel.id),
  })

  return (
    <Modal title={`Historique — ${personnel.nom_complet}`} onClose={onClose}>
      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <ul className="flex flex-col gap-3">
          {data.historique.map((remuneration, index) => (
            <li
              key={remuneration.id}
              className="rounded-xl border border-navy-100 p-3"
            >
              <div className="mb-1.5 flex items-center justify-between gap-2">
                <span className="text-sm font-semibold text-navy-900">
                  Depuis le {remuneration.date_effet.split('-').reverse().join('/')}
                </span>
                {index === 0 && <Badge tone="green">En vigueur</Badge>}
              </div>

              <dl className="grid grid-cols-3 gap-2 text-center">
                {[
                  ['Brut', remuneration.brut, 'text-navy-900'],
                  ['Net', remuneration.net, 'text-green-600'],
                  ['Coût employeur', remuneration.cout_employeur, 'text-navy-500'],
                ].map(([libelle, valeur, couleur]) => (
                  <div key={libelle as string}>
                    <dt className="text-[11px] uppercase tracking-wide text-navy-400">{libelle as string}</dt>
                    <dd className={`text-sm font-semibold tabular-nums ${couleur as string}`}>
                      {francs(valeur as number)}
                    </dd>
                  </div>
                ))}
              </dl>

              <p className="mt-2 border-t border-navy-50 pt-2 text-xs text-navy-400">
                Base {francs(remuneration.salaire_base)}
                {remuneration.prime_anciennete > 0 && ` · ancienneté ${francs(remuneration.prime_anciennete)}`}
                {remuneration.prime_transport > 0 && ` · transport ${francs(remuneration.prime_transport)}`}
                {remuneration.prime_communication > 0 && ` · communication ${francs(remuneration.prime_communication)}`}
                {remuneration.categorie && ` · catégorie ${remuneration.categorie}`}
              </p>
            </li>
          ))}

          {data.historique.length === 0 && (
            <li className="py-4 text-center text-sm text-navy-400">Aucune rémunération enregistrée.</li>
          )}
        </ul>
      )}
    </Modal>
  )
}
