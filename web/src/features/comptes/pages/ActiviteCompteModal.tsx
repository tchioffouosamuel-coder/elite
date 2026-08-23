import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { Pagination } from '@/shared/ui/Pagination'
import { fetchActiviteCompte, type CompteUtilisateur } from '@/features/comptes/api'

/** Journal d'activité d'un compte : ses connexions, et les actions marquantes dont il a été l'auteur ou la cible. */
export function ActiviteCompteModal({ compte, onClose }: { compte: CompteUtilisateur; onClose: () => void }) {
  const [page, setPage] = useState(1)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['activite-compte', compte.id, page],
    queryFn: () => fetchActiviteCompte(compte.id, page),
  })

  return (
    <Modal title={`Activité — ${compte.nom}`} onClose={onClose}>
      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.items.length === 0 ? (
        <EmptyState label="Aucune activité enregistrée pour ce compte." />
      ) : (
        <>
          <ul className="flex flex-col divide-y divide-navy-50">
            {data.items.map((entree) => (
              <li key={entree.id} className="py-2.5">
                <div className="flex items-start justify-between gap-3">
                  <p className="text-sm text-navy-800">{entree.description}</p>
                  <span className="flex-none whitespace-nowrap text-xs text-navy-400">
                    {new Date(entree.created_at).toLocaleString('fr-FR', {
                      day: '2-digit',
                      month: '2-digit',
                      year: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </span>
                </div>
                <div className="mt-0.5 text-xs text-navy-400">
                  {entree.causer_nom}
                  {entree.causer_role && ` · ${entree.causer_role}`}
                </div>
              </li>
            ))}
          </ul>
          <div className="mt-2">
            <Pagination pagination={data.pagination} onChange={setPage} />
          </div>
        </>
      )}
    </Modal>
  )
}
