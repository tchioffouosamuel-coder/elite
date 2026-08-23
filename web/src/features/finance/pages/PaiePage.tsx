import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Banknote, FileText, Lock, Wallet, PenLine, Users, ListChecks, Play } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card, StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, erreur, info, succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import {
  arreterBulletin,
  arreterBulletins,
  emargerBulletin,
  fetchPaie,
  francs,
  payerBulletin,
  payerBulletins,
  preparerPaie,
  preparerBulletinAgent,
  fetchBordereauVirement,
  type AgentIgnore,
  type BulletinPaie,
} from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

const MOIS = [
  'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
  'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
]

const TONS = { brouillon: 'neutral', valide: 'gold', paye: 'green' } as const
const LIBELLES = { brouillon: 'Brouillon', valide: 'Arrêté', paye: 'Payé' } as const

/**
 * Paie du mois.
 *
 * Les trois états du bulletin structurent l'écran : on prépare en lot, on
 * arrête bulletin par bulletin — c'est irréversible —, puis on règle et on
 * fait émarger. Chaque action n'apparaît qu'à l'étape où elle a un sens.
 */
export function PaiePage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const queryClient = useQueryClient()

  const aujourdhui = new Date()
  const [annee, setAnnee] = useState(aujourdhui.getFullYear())
  const [mois, setMois] = useState(aujourdhui.getMonth() + 1)
  const [enCours, setEnCours] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  // Vacataires que le lot n'a pas pu préparer faute d'heures : c'est ici
  // qu'on les leur demande, un par un.
  const [vacatairesEnAttente, setVacatairesEnAttente] = useState<AgentIgnore[]>([])

  const { data, isLoading, isError } = useQuery({
    queryKey: ['paie', activeSchoolId, annee, mois],
    queryFn: () => fetchPaie({ annee, mois }),
  })

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['paie'] })

  // La liste des vacataires en attente ne vaut que pour le mois où elle a été
  // établie : changer de période sans la vider laisserait croire qu'ils
  // manquent toujours ici, alors que le lot n'a simplement pas encore tourné.
  useEffect(() => {
    setVacatairesEnAttente([])
  }, [annee, mois])

  const handleToggleSelect = (id: number) => {
    const newSelected = new Set(selectedIds)
    if (newSelected.has(id)) {
      newSelected.delete(id)
    } else {
      newSelected.add(id)
    }
    setSelectedIds(newSelected)
  }

  const handleSelectAll = (bulletins: BulletinPaie[]) => {
    if (selectedIds.size === bulletins.length && bulletins.length > 0) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(bulletins.map((b) => b.id)))
    }
  }

  const arreterSelection = async () => {
    // Seuls les brouillons se laissent arrêter : un bulletin déjà arrêté ou
    // payé, s'il traîne dans la sélection, est simplement ignoré plutôt que
    // de faire échouer tout le lot.
    const cibles = (data?.bulletins ?? []).filter((b) => selectedIds.has(b.id) && b.statut === 'brouillon')
    if (cibles.length === 0) return

    const ok = await confirmer({
      titre: `Arrêter ${cibles.length} bulletin(s) ?`,
      message: 'Un bulletin arrêté ne se recalcule plus et rejoint la comptabilité. Cette action est irréversible.',
      action: 'Arrêter',
      destructif: false,
    })
    if (!ok) return

    try {
      const { arretes } = await arreterBulletins(cibles.map((b) => b.id))
      setSelectedIds(new Set())
      succes(`${arretes} bulletin(s) arrêté(s).`)
      rafraichir()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    }
  }

  const payerSelection = async () => {
    // Seuls les bulletins arrêtés se règlent : un brouillon ou un bulletin
    // déjà payé, s'il traîne dans la sélection, est simplement ignoré plutôt
    // que de faire échouer tout le lot.
    const cibles = (data?.bulletins ?? []).filter((b) => selectedIds.has(b.id) && b.statut === 'valide')
    if (cibles.length === 0) return

    const ok = await confirmer({
      titre: `Marquer réglés ${cibles.length} bulletin(s) ?`,
      message: `Net total à décaisser : ${francs(cibles.reduce((s, b) => s + b.net_a_payer, 0))}, réglé en espèces.`,
      action: 'Payer',
      destructif: false,
    })
    if (!ok) return

    try {
      const { regles } = await payerBulletins(
        cibles.map((b) => b.id),
        'especes',
      )
      setSelectedIds(new Set())
      succes(`${regles} bulletin(s) réglé(s).`)
      rafraichir()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    }
  }

  const agir = async (action: () => Promise<void>, message: string) => {
    try {
      await action()
      succes(message)
      rafraichir()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    }
  }

  const preparer = async () => {
    setEnCours(true)
    try {
      const { prepares, ignores } = await preparerPaie({ annee, mois }, { jours_ouvrables: 22 })
      succes(t('finance.bulletins_prepared', { prepares }))

      const vacataires = ignores.filter((i) => i.motif === 'heures_requises')
      const sansRemuneration = ignores.filter((i) => i.motif === 'sans_remuneration')

      setVacatairesEnAttente(vacataires)

      // Un agent sans rémunération définie est presque toujours un oubli de
      // saisie : le taire reviendrait à le payer zéro sans le dire. Les
      // vacataires, eux, ont leur propre encart ci-dessous — inutile de les
      // répéter dans ce toast.
      if (sansRemuneration.length > 0) {
        info(
          t('finance.staff_without_remuneration', {
            count: sansRemuneration.length,
            names: sansRemuneration.slice(0, 3).map((i) => i.nom_complet).join(' · '),
          }),
        )
      }
      rafraichir()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    } finally {
      setEnCours(false)
    }
  }

  const preparerVacataire = async (agent: AgentIgnore, heures: number) => {
    try {
      await preparerBulletinAgent(agent.personnel_id, { annee, mois }, { heures })
      setVacatairesEnAttente((liste) => liste.filter((a) => a.personnel_id !== agent.personnel_id))
      succes(`Bulletin de ${agent.nom_complet} préparé pour ${heures} h.`)
      rafraichir()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    }
  }

  const arreter = async (bulletin: BulletinPaie) => {
    const ok = await confirmer({
      titre: `Arrêter le bulletin de ${bulletin.personnel.nom_complet} ?`,
      message: `Net à payer : ${francs(bulletin.net_a_payer)}. Un bulletin arrêté ne se recalcule plus et rejoint la comptabilité.`,
      action: 'Arrêter',
      destructif: false,
    })
    if (ok) await agir(() => arreterBulletin(bulletin.id), t('finance.bulletin_stopped'))
  }

  const colonnes: Colonne<BulletinPaie>[] = [
    ...(can('finance.paie')
      ? [
          {
            cle: 'selection',
            sticky: 'left',
            largeur: '44px',
            entete: data?.bulletins ? (
              <input
                type="checkbox"
                checked={selectedIds.size === data.bulletins.length && data.bulletins.length > 0}
                onChange={() => handleSelectAll(data?.bulletins ?? [])}
                className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
              />
            ) : null,
            cellule: (b: BulletinPaie) => (
              <input
                type="checkbox"
                checked={selectedIds.has(b.id)}
                onChange={() => handleToggleSelect(b.id)}
                className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
              />
            ),
          } satisfies Colonne<BulletinPaie>,
        ]
      : []),
    {
      cle: 'agent',
      entete: 'Agent',
      largeur: '220px',
      valeur: (b) => `${b.personnel.nom_complet} ${b.personnel.matricule ?? ''}`,
      cellule: (b) => (
        <div className="min-w-0">
          <div className="truncate font-semibold text-navy-900">{b.personnel.nom_complet}</div>
          <div className="truncate text-xs text-navy-400">
            {b.personnel.matricule ?? '—'} · {b.personnel.fonction ?? 'Sans fonction'}
          </div>
        </div>
      ),
    },
    {
      cle: 'brut',
      entete: 'Brut',
      largeur: '100px',
      valeur: (b) => b.salaire_brut,
      cellule: (b) => <span className="tabular-nums">{francs(b.salaire_brut)}</span>,
      masquerMobile: true,
    },
    {
      cle: 'retenues',
      entete: 'Retenues',
      largeur: '100px',
      valeur: (b) => b.charges_salariales + b.total_deductions,
      cellule: (b) => (
        <span className="tabular-nums text-red-500">{francs(b.charges_salariales + b.total_deductions)}</span>
      ),
      masquerMobile: true,
    },
    {
      cle: 'net',
      entete: 'Net à payer',
      largeur: '120px',
      valeur: (b) => b.net_a_payer,
      cellule: (b) => <span className="font-semibold tabular-nums text-green-600">{francs(b.net_a_payer)}</span>,
    },
    {
      cle: 'statut',
      entete: 'Statut',
      largeur: '150px',
      valeur: (b) => b.statut,
      cellule: (b) => (
        <div className="flex items-center gap-1.5">
          <Badge tone={TONS[b.statut]}>{LIBELLES[b.statut]}</Badge>
          {b.emarge && <Badge tone="blue">Émargé</Badge>}
        </div>
      ),
    },
    {
      cle: 'actions',
      entete: '',
      sticky: 'right',
      largeur: '110px',
      cellule: (b) => (
        <div className="flex justify-end gap-1.5">
          <Button
            size="sm"
            variant="secondary"
            title="Bulletin PDF"
            onClick={() => ouvrirDocument(`/paie/bulletins/${b.id}/pdf`)}
          >
            <FileText className="h-3.5 w-3.5" />
          </Button>

          {can('finance.paie') && b.statut === 'brouillon' && (
            <Button size="sm" title="Arrêter" onClick={() => arreter(b)}>
              <Lock className="h-3.5 w-3.5" />
            </Button>
          )}

          {can('finance.paie') && b.statut === 'valide' && (
            <Button
              size="sm"
              title="Marquer réglé"
              onClick={() => agir(() => payerBulletin(b.id, 'especes'), 'Règlement enregistré.')}
            >
              <Wallet className="h-3.5 w-3.5" />
            </Button>
          )}

          {can('finance.paie') && b.statut === 'paye' && !b.emarge && (
            <Button
              size="sm"
              variant="secondary"
              title="Enregistrer l'émargement"
              onClick={() => agir(() => emargerBulletin(b.id), 'Émargement enregistré.')}
            >
              <PenLine className="h-3.5 w-3.5" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  const eligiblesArreter = (data?.bulletins ?? []).filter((b) => selectedIds.has(b.id) && b.statut === 'brouillon').length
  const eligiblesPayer = (data?.bulletins ?? []).filter((b) => selectedIds.has(b.id) && b.statut === 'valide').length

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Paie du personnel"
        sousTitre="Préparation, arrêté, règlement et émargement."
        icon={Banknote}
        actions={
          <>
            <Button variant="secondary" onClick={() => ouvrirDocument('/paie/etat-emargement', { annee, mois })}>
              <ListChecks className="h-4 w-4" />
              État d'émargement
            </Button>
            {can('finance.paie') && (
              <Button onClick={preparer} disabled={enCours}>
                <Play className="h-4 w-4" />
                {enCours ? 'Préparation…' : 'Préparer la paie'}
              </Button>
            )}
          </>
        }
      />

      {selectedIds.size > 0 && can('finance.paie') && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="font-medium text-navy-900">
              {selectedIds.size} bulletin(s) sélectionné(s)
              {eligiblesArreter === 0 && eligiblesPayer === 0 && (
                <span className="ml-1.5 font-normal text-navy-500">— déjà arrêtés ou payés, aucune action possible</span>
              )}
            </p>
            <div className="flex flex-wrap gap-2">
              {eligiblesArreter > 0 && (
                <Button onClick={arreterSelection}>
                  <Lock className="h-4 w-4" />
                  Arrêter ({eligiblesArreter})
                </Button>
              )}
              {eligiblesPayer > 0 && (
                <Button onClick={payerSelection}>
                  <Wallet className="h-4 w-4" />
                  Payer ({eligiblesPayer})
                </Button>
              )}
              <button
                onClick={() => setSelectedIds(new Set())}
                className="rounded-lg px-4 py-2 text-sm font-medium text-navy-600 hover:bg-navy-50 whitespace-nowrap"
              >
                Annuler
              </button>
            </div>
          </div>
        </div>
      )}

      {vacatairesEnAttente.length > 0 && (
        <VacatairesEnAttenteCard agents={vacatairesEnAttente} onPreparer={preparerVacataire} />
      )}

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Effectif" value={data.totaux.effectif} icon={Users} accent="navy" />
            <StatCard label="Masse brute" value={francs(data.totaux.brut)} icon={Banknote} accent="gold" />
            <StatCard
              label="Coût employeur"
              value={francs(data.totaux.cout_employeur)}
              icon={Banknote}
              accent="red"
              hint="charges patronales comprises"
            />
            <StatCard
              label="Net à décaisser"
              value={francs(data.totaux.net_a_payer)}
              icon={Wallet}
              accent="green"
              hint={`${data.totaux.regles} réglé(s) · ${data.totaux.emarges} émargé(s)`}
            />
          </div>

          <DataTable
            colonnes={colonnes}
            lignes={data.bulletins}
            cleLigne={(b) => b.id}
            placeholderRecherche={t('finance.search_paie')}
            messageVide={t('finance.empty_paie')}
            largeurMin={600}
            outils={
              <div className="flex gap-2">
                <Select value={mois} onChange={(e) => setMois(Number(e.target.value))}>
                  {MOIS.map((libelle, index) => (
                    <option key={libelle} value={index + 1}>
                      {libelle}
                    </option>
                  ))}
                </Select>
                <Select value={annee} onChange={(e) => setAnnee(Number(e.target.value))}>
                  {[0, 1, 2, 3].map((decalage) => {
                    const valeur = aujourdhui.getFullYear() - decalage
                    return (
                      <option key={valeur} value={valeur}>
                        {valeur}
                      </option>
                    )
                  })}
                </Select>
              </div>
            }
          />

          <BordereauCard annee={annee} mois={mois} />
        </>
      )}
    </div>
  )
}

/**
 * Dernière étape du circuit : ce qui part à la banque. Un bloc par
 * établissement bancaire, comme au bordereau papier, avec l'appoint d'arrondi
 * qui reste en caisse.
 */
function BordereauCard({ annee, mois }: { annee: number; mois: number }) {
  const activeSchool = useAuthStore((s) => s.activeSchool)
  const user = useAuthStore((s) => s.user)
  const schoolId = activeSchool()?.id ?? user?.ecoles_accessibles?.[0]?.id ?? null

  const { data } = useQuery({
    queryKey: ['bordereau-virement', schoolId, annee, mois],
    queryFn: () => fetchBordereauVirement(schoolId as number, annee, mois),
    enabled: schoolId !== null,
  })

  if (!data || (data.banques.length === 0 && data.sans_domiciliation.length === 0)) return null

  return (
    <Card>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-bold uppercase tracking-wide text-navy-500">Bordereau de virement</h2>
          <p className="mt-0.5 text-xs text-navy-400">Bulletins arrêtés du mois, rangés par banque.</p>
        </div>
        <div className="flex items-center gap-3">
          <span className="font-display text-lg font-bold tabular-nums text-navy-900">{francs(data.total)}</span>
          <Button
            variant="secondary"
            onClick={() => ouvrirDocument('/paie/bordereau/pdf', { school_id: schoolId as number, annee, mois })}
          >
            <FileText className="h-4 w-4" />
            PDF
          </Button>
        </div>
      </div>

      <div className="flex flex-col gap-4">
        {data.banques.map((banque) => (
          <div key={banque.banque}>
            <div className="mb-1.5 flex items-center justify-between gap-3 border-b border-navy-100 pb-1.5">
              <span className="text-xs font-bold uppercase tracking-wide text-navy-700">
                {banque.banque}
                <span className="ml-2 font-medium text-navy-400">{banque.effectif} agent(s)</span>
              </span>
              <span className="font-semibold tabular-nums text-navy-800">{francs(banque.total)}</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[30rem] text-sm tabular-nums">
                <tbody className="divide-y divide-navy-50">
                  {banque.lignes.map((ligne) => (
                    <tr key={ligne.bulletin_id}>
                      <td className="py-1.5 pr-3 text-navy-700">{ligne.nom_complet}</td>
                      <td className="py-1.5 pr-3 font-mono text-xs text-navy-400">{ligne.numero_compte}</td>
                      <td className="py-1.5 text-right font-semibold text-navy-800">{francs(ligne.montant)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        ))}

        {data.sans_domiciliation.length > 0 && (
          <div className="rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-800">
            <b>{data.sans_domiciliation.length} agent(s) sans domiciliation bancaire</b> — hors bordereau tant que la
            banque et le numéro de compte ne sont pas renseignés :{' '}
            {data.sans_domiciliation.map((l) => l.nom_complet).join(', ')}.
          </div>
        )}
      </div>
    </Card>
  )
}

/**
 * Vacataires du technique : le lot n'a pas pu les préparer, faute d'heures —
 * une saisie globale (jours ouvrables) n'a pas de sens pour eux. Une ligne
 * par agent, un champ heures, un bouton : c'est ici que leur bulletin naît.
 */
function VacatairesEnAttenteCard({
  agents,
  onPreparer,
}: {
  agents: AgentIgnore[]
  onPreparer: (agent: AgentIgnore, heures: number) => Promise<void>
}) {
  const [heures, setHeures] = useState<Record<number, string>>({})
  const [enCours, setEnCours] = useState<number | null>(null)

  const preparerLigne = async (agent: AgentIgnore) => {
    const valeur = Number(heures[agent.personnel_id] ?? 0)
    if (!Number.isFinite(valeur) || valeur <= 0) return

    setEnCours(agent.personnel_id)
    try {
      await onPreparer(agent, valeur)
    } finally {
      setEnCours(null)
    }
  }

  return (
    <Card>
      <div className="mb-3">
        <h2 className="text-sm font-bold uppercase tracking-wide text-navy-500">
          Vacataires à préparer ({agents.length})
        </h2>
        <p className="mt-0.5 text-xs text-navy-400">
          Payés à l'heure, sans salaire de base : leurs heures du mois manquent au lot préparé ci-dessus.
        </p>
      </div>

      <div className="flex flex-col divide-y divide-navy-50">
        {agents.map((agent) => (
          <div key={agent.personnel_id} className="flex flex-wrap items-center justify-between gap-3 py-2.5">
            <span className="font-medium text-navy-800">{agent.nom_complet}</span>
            <div className="flex items-center gap-2">
              <input
                type="number"
                min={0}
                max={744}
                placeholder="Heures du mois"
                value={heures[agent.personnel_id] ?? ''}
                onChange={(e) => setHeures((h) => ({ ...h, [agent.personnel_id]: e.target.value }))}
                className="w-32 rounded-lg border border-navy-200 px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
              />
              <Button
                size="sm"
                onClick={() => preparerLigne(agent)}
                disabled={enCours === agent.personnel_id || !Number(heures[agent.personnel_id] ?? 0)}
              >
                <Play className="h-3.5 w-3.5" />
                Préparer
              </Button>
            </div>
          </div>
        ))}
      </div>
    </Card>
  )
}
