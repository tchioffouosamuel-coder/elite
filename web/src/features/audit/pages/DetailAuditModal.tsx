import { useQuery } from '@tanstack/react-query'
import { Modal } from '@/shared/ui/Modal'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { fetchDetailAudit, type ChangementAudit } from '@/features/audit/api'
import { TON_ACTION, formaterHorodatage, libelleModule } from '@/features/audit/libelles'

const LIBELLE_OPERATION: Record<ChangementAudit['operation'], { libelle: string; ton: 'green' | 'gold' | 'red' }> = {
  created: { libelle: 'Création', ton: 'green' },
  updated: { libelle: 'Modification', ton: 'gold' },
  deleted: { libelle: 'Suppression', ton: 'red' },
}

function valeur(v: unknown): string {
  if (v === null || v === undefined) return '∅'
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

function Ligne({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-[8rem_1fr] gap-3 py-1.5 text-sm">
      <dt className="text-navy-400">{label}</dt>
      <dd className="min-w-0 break-words text-navy-800">{children}</dd>
    </div>
  )
}

function TableChangement({ changement }: { changement: ChangementAudit }) {
  const champs = Array.from(new Set([...Object.keys(changement.avant ?? {}), ...Object.keys(changement.apres ?? {})]))
  const operation = LIBELLE_OPERATION[changement.operation]

  return (
    <div className="rounded-xl border border-navy-100">
      <div className="flex items-center gap-2 border-b border-navy-50 px-3 py-2">
        <Badge tone={operation.ton}>{operation.libelle}</Badge>
        <span className="font-mono text-xs text-navy-600">
          {changement.modele} #{changement.id ?? '?'}
        </span>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-xs">
          <thead className="bg-cream-50 text-left text-navy-500">
            <tr>
              <th className="px-3 py-1.5 font-semibold">Champ</th>
              {changement.operation !== 'created' && <th className="px-3 py-1.5 font-semibold">Avant</th>}
              {changement.operation !== 'deleted' && <th className="px-3 py-1.5 font-semibold">Après</th>}
            </tr>
          </thead>
          <tbody className="divide-y divide-navy-50 font-mono">
            {champs.map((champ) => (
              <tr key={champ}>
                <td className="px-3 py-1.5 text-navy-600">{champ}</td>
                {changement.operation !== 'created' && (
                  <td className="px-3 py-1.5 break-all text-red-600">{valeur(changement.avant?.[champ])}</td>
                )}
                {changement.operation !== 'deleted' && (
                  <td className="px-3 py-1.5 break-all text-green-700">{valeur(changement.apres?.[champ])}</td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

/** Détail complet d'une entrée du journal : contexte, données envoyées et écritures en base. */
export function DetailAuditModal({ id, onClose }: { id: number; onClose: () => void }) {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['audit-detail', id],
    queryFn: () => fetchDetailAudit(id),
  })

  return (
    <Modal title={`Entrée d'audit #${id}`} onClose={onClose} taille="lg">
      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <div className="flex flex-col gap-5">
          <dl className="divide-y divide-navy-50">
            <Ligne label="Date et heure">{formaterHorodatage(data.created_at)}</Ligne>
            <Ligne label="Utilisateur">
              {data.user_nom ?? <span className="text-navy-400">Anonyme</span>}
              {data.user_role && <span className="text-navy-400"> · {data.user_role}</span>}
              {data.user_id && <span className="text-navy-400"> (compte #{data.user_id})</span>}
            </Ligne>
            <Ligne label="Action">
              <Badge tone={TON_ACTION[data.action] ?? 'neutral'}>{data.action_libelle}</Badge>
            </Ligne>
            <Ligne label="Module">{libelleModule(data.module)}</Ligne>
            <Ligne label="École">{data.school ?? '—'}</Ligne>
            <Ligne label="Requête">
              <span className="font-mono text-xs">
                {data.methode} {data.url}
              </span>
            </Ligne>
            <Ligne label="Route">
              <span className="font-mono text-xs">{data.route ?? '—'}</span>
            </Ligne>
            {data.parametres && Object.keys(data.parametres).length > 0 && (
              <Ligne label="Paramètres">
                <span className="font-mono text-xs">{JSON.stringify(data.parametres)}</span>
              </Ligne>
            )}
            <Ligne label="Résultat">
              <Badge tone={data.statut_http >= 400 ? 'red' : 'green'}>HTTP {data.statut_http}</Badge>
              {data.duree_ms !== null && <span className="ml-2 text-xs text-navy-400">{data.duree_ms} ms</span>}
            </Ligne>
            <Ligne label="Adresse IP">
              <span className="font-mono text-xs">{data.ip_address ?? '—'}</span>
            </Ligne>
            <Ligne label="Navigateur">
              <span className="text-xs">{data.user_agent ?? '—'}</span>
            </Ligne>
          </dl>

          {data.donnees && (
            <section className="flex flex-col gap-2">
              <h3 className="text-sm font-semibold text-navy-800">Données envoyées</h3>
              <pre className="max-h-64 overflow-auto rounded-xl bg-navy-900 p-3 text-xs text-cream-50">
                {JSON.stringify(data.donnees, null, 2)}
              </pre>
            </section>
          )}

          <section className="flex flex-col gap-2">
            <h3 className="text-sm font-semibold text-navy-800">
              Enregistrements modifiés {data.changements ? `(${data.changements.length})` : ''}
            </h3>
            {data.changements && data.changements.length > 0 ? (
              data.changements.map((c, i) => <TableChangement key={i} changement={c} />)
            ) : (
              <p className="text-sm text-navy-400">Aucune écriture en base pendant cette requête.</p>
            )}
          </section>
        </div>
      )}
    </Modal>
  )
}
