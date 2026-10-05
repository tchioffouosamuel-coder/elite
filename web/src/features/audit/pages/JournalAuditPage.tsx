import { useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Activity, AlertTriangle, Eraser, LogIn, PencilLine, Radio, RefreshCw, ScrollText, Search, Users } from 'lucide-react'
import { clsx } from 'clsx'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card, StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input, Select } from '@/shared/ui/Field'
import { Table, Thead, Th, Td, Tr } from '@/shared/ui/Table'
import { Pagination } from '@/shared/ui/Pagination'
import { ExportButton } from '@/shared/ui/ExportButton'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import {
  fetchJournalAudit,
  fetchOptionsFiltresAudit,
  fetchStatsAudit,
  parametresExportAudit,
  type FiltresAudit,
} from '@/features/audit/api'
import { TON_ACTION, dateIso, formaterHorodatage, libelleModule } from '@/features/audit/libelles'
import { DetailAuditModal } from '@/features/audit/pages/DetailAuditModal'

const INTERVALLE_DIRECT_MS = 5000

const PERIODES = [
  { code: 'jour', libelle: "Aujourd'hui", jours: 0 },
  { code: 'semaine', libelle: '7 jours', jours: 6 },
  { code: 'mois', libelle: '30 jours', jours: 29 },
] as const

function filtresPeriode(jours: number): Pick<FiltresAudit, 'du' | 'au'> {
  const debut = new Date()
  debut.setDate(debut.getDate() - jours)
  return { du: dateIso(debut), au: dateIso(new Date()) }
}

/**
 * Console d'audit (interface développeur), réservée au super administrateur :
 * TOUTES les requêtes faites sur le système — connexions, consultations,
 * créations, modifications, suppressions, exports… — horodatées et
 * attribuées à leur auteur, avec le détail avant/après de chaque écriture.
 *
 * Le mode « En direct » rafraîchit la liste toutes les 5 s sans polluer le
 * journal : ces appels automatiques sont signalés au serveur, qui ne les
 * journalise pas (cf. config `audit.routes_actualisation_auto`).
 */
export function JournalAuditPage() {
  const [filtres, setFiltres] = useState<FiltresAudit>(filtresPeriode(0))
  const [recherche, setRecherche] = useState('')
  const [page, setPage] = useState(1)
  const [direct, setDirect] = useState(false)
  const [detailId, setDetailId] = useState<number | null>(null)

  const appliquer = (modif: Partial<FiltresAudit>) => {
    setFiltres((f) => ({ ...f, ...modif }))
    setPage(1)
  }

  const optionsRequete = {
    refetchInterval: direct ? INTERVALLE_DIRECT_MS : (false as const),
    refetchOnWindowFocus: false,
    placeholderData: keepPreviousData,
  }

  const journal = useQuery({
    queryKey: ['audit', filtres, page, direct],
    queryFn: () => fetchJournalAudit(filtres, page, direct),
    ...optionsRequete,
  })

  const stats = useQuery({
    queryKey: ['audit-stats', filtres, direct],
    queryFn: () => fetchStatsAudit(filtres, direct),
    ...optionsRequete,
  })

  const options = useQuery({
    queryKey: ['audit-filtres'],
    queryFn: fetchOptionsFiltresAudit,
    refetchOnWindowFocus: false,
  })

  const s = stats.data
  const ecritures = s ? (s.par_action.creation ?? 0) + (s.par_action.modification ?? 0) + (s.par_action.suppression ?? 0) : 0
  const connexions = s ? (s.par_action.connexion ?? 0) : 0
  const echecs = s ? (s.par_action.connexion_echouee ?? 0) : 0

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Console d'audit"
        sousTitre="Journal de toutes les actions effectuées sur le système"
        icon={ScrollText}
        actions={
          <>
            <Button
              type="button"
              variant={direct ? 'primary' : 'secondary'}
              onClick={() => setDirect((d) => !d)}
              title="Rafraîchir automatiquement toutes les 5 secondes"
            >
              <Radio className={clsx('h-4 w-4', direct && 'animate-pulse')} />
              {direct ? 'En direct' : 'Direct désactivé'}
            </Button>
            <Button
              type="button"
              variant="secondary"
              onClick={() => {
                journal.refetch()
                stats.refetch()
              }}
              disabled={journal.isFetching}
            >
              <RefreshCw className={clsx('h-4 w-4', journal.isFetching && 'animate-spin')} />
              Actualiser
            </Button>
            <ExportButton url="/audit/export" params={parametresExportAudit(filtres)} nomFichier="journal-audit.csv" label="Exporter CSV" />
          </>
        }
      />

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <StatCard label="Actions" value={s?.total ?? '—'} icon={Activity} />
        <StatCard label="Connexions" value={s ? connexions : '—'} icon={LogIn} accent="green" />
        <StatCard
          label="Connexions échouées"
          value={s ? echecs : '—'}
          icon={AlertTriangle}
          accent={echecs > 0 ? 'red' : 'navy'}
          onClick={() => appliquer({ action: 'connexion_echouee' })}
        />
        <StatCard label="Créations / modifs / suppressions" value={s ? ecritures : '—'} icon={PencilLine} accent="gold" />
        <StatCard label="Utilisateurs actifs" value={s?.utilisateurs_distincts ?? '—'} icon={Users} />
      </div>

      <Card>
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-center gap-2">
            {PERIODES.map((p) => {
              const periode = filtresPeriode(p.jours)
              const actif = filtres.du === periode.du && filtres.au === periode.au
              return (
                <Button key={p.code} type="button" size="sm" variant={actif ? 'primary' : 'secondary'} onClick={() => appliquer(periode)}>
                  {p.libelle}
                </Button>
              )
            })}
            <Button type="button" size="sm" variant={!filtres.du && !filtres.au ? 'primary' : 'secondary'} onClick={() => appliquer({ du: undefined, au: undefined })}>
              Tout
            </Button>
            <Button
              type="button"
              size="sm"
              variant="ghost"
              onClick={() => {
                setRecherche('')
                setFiltres(filtresPeriode(0))
                setPage(1)
              }}
            >
              <Eraser className="h-3.5 w-3.5" />
              Réinitialiser
            </Button>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Input label="Du" type="date" value={filtres.du ?? ''} onChange={(e) => appliquer({ du: e.target.value || undefined })} />
            <Input label="Au" type="date" value={filtres.au ?? ''} onChange={(e) => appliquer({ au: e.target.value || undefined })} />
            <Select label="Utilisateur" value={filtres.user_id ?? ''} onChange={(e) => appliquer({ user_id: e.target.value || undefined })}>
              <option value="">Tous les utilisateurs</option>
              {options.data?.utilisateurs.map((u) => (
                <option key={u.user_id} value={String(u.user_id)}>
                  {u.user_nom}
                  {u.user_role ? ` — ${u.user_role}` : ''}
                </option>
              ))}
            </Select>
            <Select label="Action" value={filtres.action ?? ''} onChange={(e) => appliquer({ action: e.target.value || undefined })}>
              <option value="">Toutes les actions</option>
              {options.data?.actions.map((a) => (
                <option key={a.code} value={a.code}>
                  {a.libelle}
                </option>
              ))}
            </Select>
            <Select label="Module" value={filtres.module ?? ''} onChange={(e) => appliquer({ module: e.target.value || undefined })}>
              <option value="">Tous les modules</option>
              {options.data?.modules.map((m) => (
                <option key={m} value={m}>
                  {libelleModule(m)}
                </option>
              ))}
            </Select>
            <Select label="Méthode" value={filtres.methode ?? ''} onChange={(e) => appliquer({ methode: e.target.value || undefined })}>
              <option value="">Toutes</option>
              {['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map((m) => (
                <option key={m} value={m}>
                  {m}
                </option>
              ))}
            </Select>
            <Select label="Résultat" value={filtres.statut ?? ''} onChange={(e) => appliquer({ statut: e.target.value || undefined })}>
              <option value="">Tous</option>
              <option value="succes">Succès</option>
              <option value="erreur">Erreurs (4xx / 5xx)</option>
            </Select>
            <form
              onSubmit={(e) => {
                e.preventDefault()
                appliquer({ recherche: recherche.trim() || undefined })
              }}
            >
              <Input
                label="Recherche"
                icon={Search}
                placeholder="Nom, URL, route… puis Entrée"
                value={recherche}
                onChange={(e) => setRecherche(e.target.value)}
                onBlur={() => appliquer({ recherche: recherche.trim() || undefined })}
              />
            </form>
          </div>
        </div>
      </Card>

      {s && (s.top_utilisateurs.length > 0 || s.top_modules.length > 0) && (
        <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
          <Card>
            <h3 className="mb-2 text-sm font-semibold text-navy-800">Utilisateurs les plus actifs</h3>
            <ul className="flex flex-col gap-1.5">
              {s.top_utilisateurs.map((u) => (
                <li key={u.user_id}>
                  <button
                    type="button"
                    className="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-1 text-left text-sm hover:bg-cream-50"
                    onClick={() => appliquer({ user_id: String(u.user_id) })}
                  >
                    <span className="truncate text-navy-800">{u.user_nom}</span>
                    <span className="font-mono text-xs text-navy-500">{u.total}</span>
                  </button>
                </li>
              ))}
            </ul>
          </Card>
          <Card>
            <h3 className="mb-2 text-sm font-semibold text-navy-800">Modules les plus sollicités</h3>
            <ul className="flex flex-col gap-1.5">
              {s.top_modules.map((m) => (
                <li key={m.module}>
                  <button
                    type="button"
                    className="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-1 text-left text-sm hover:bg-cream-50"
                    onClick={() => appliquer({ module: m.module })}
                  >
                    <span className="truncate text-navy-800">{libelleModule(m.module)}</span>
                    <span className="font-mono text-xs text-navy-500">{m.total}</span>
                  </button>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      )}

      {journal.isLoading ? (
        <Spinner />
      ) : journal.isError || !journal.data ? (
        <ErrorState />
      ) : journal.data.items.length === 0 ? (
        <EmptyState label="Aucune action enregistrée pour ces critères." />
      ) : (
        <>
          <Table minWidth={1100}>
            <Thead>
              <tr>
                <Th>Date et heure</Th>
                <Th>Utilisateur</Th>
                <Th>Action</Th>
                <Th>Module</Th>
                <Th>Requête</Th>
                <Th>Résultat</Th>
                <Th>IP</Th>
              </tr>
            </Thead>
            <tbody>
              {journal.data.items.map((e) => (
                <Tr key={e.id} className="cursor-pointer" onClick={() => setDetailId(e.id)}>
                  <Td className="whitespace-nowrap font-mono text-xs">{formaterHorodatage(e.created_at)}</Td>
                  <Td>
                    <div className="font-medium">{e.user_nom ?? <span className="text-navy-400">Anonyme</span>}</div>
                    {e.user_role && <div className="text-xs text-navy-400">{e.user_role}</div>}
                  </Td>
                  <Td>
                    <Badge tone={TON_ACTION[e.action] ?? 'neutral'}>{e.action_libelle}</Badge>
                    {e.nb_changements > 0 && (
                      <div className="mt-1 text-xs text-navy-400">
                        {e.nb_changements} enregistrement{e.nb_changements > 1 ? 's' : ''}
                      </div>
                    )}
                  </Td>
                  <Td className="whitespace-nowrap">{libelleModule(e.module)}</Td>
                  <Td>
                    <div className="max-w-md truncate font-mono text-xs" title={e.url}>
                      <span className="font-semibold">{e.methode}</span> {e.url}
                    </div>
                  </Td>
                  <Td className="whitespace-nowrap">
                    <Badge tone={e.statut_http >= 400 ? 'red' : 'green'}>{e.statut_http}</Badge>
                    {e.duree_ms !== null && <span className="ml-2 text-xs text-navy-400">{e.duree_ms} ms</span>}
                  </Td>
                  <Td className="whitespace-nowrap font-mono text-xs">{e.ip_address ?? '—'}</Td>
                </Tr>
              ))}
            </tbody>
          </Table>
          <Pagination pagination={journal.data.pagination} onChange={setPage} />
        </>
      )}

      {detailId !== null && <DetailAuditModal id={detailId} onClose={() => setDetailId(null)} />}
    </div>
  )
}
