import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Ban, CheckCircle2, FileBarChart, Paperclip, Plus, ReceiptText, TrendingDown, Wallet } from 'lucide-react'
import { fetchVehicule } from '@/features/bus/api'
import { annulerDepense, fetchDepenses, francs, payerDepense, type Depense } from '@/features/finance/api'
import { DepenseFormModal } from '@/features/finance/pages/DepenseFormModal'
import { ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { PageHeader } from '@/shared/ui/PageHeader'
import { StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TONS = { payee: 'green', engagee: 'gold', annulee: 'red' } as const
const LIBELLES = { payee: 'Payée', engagee: 'Engagée', annulee: 'Annulée' } as const

/**
 * Dépenses d'un véhicule précis (maintenance, entretien, carburant…) — la
 * même mécanique que le suivi général des dépenses, simplement filtrée et
 * pré-remplie sur ce bus, pour que la saisie au quotidien n'ait pas à
 * ressélectionner le véhicule à chaque ligne.
 */
export function BusVehiculeDepensesPage() {
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const { id } = useParams<{ id: string }>()
  const vehiculeId = Number(id)

  const [du, setDu] = useState('')
  const [au, setAu] = useState('')
  const [formOuvert, setFormOuvert] = useState(false)

  const { data: vehicule, isLoading: vehiculeEnChargement } = useQuery({
    queryKey: ['bus-vehicule', vehiculeId],
    queryFn: () => fetchVehicule(vehiculeId),
  })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['depenses-vehicule', vehiculeId, du, au],
    queryFn: () => fetchDepenses({ du: du || null, au: au || null, vehicule_id: vehiculeId }),
  })

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['depenses-vehicule', vehiculeId] })

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

  const annuler = async (depense: Depense) => {
    const ok = await confirmer({
      titre: 'Annuler cette dépense ?',
      message: `${depense.libelle} — ${francs(depense.montant)}. La dépense reste au registre et son écriture est contrepassée.`,
      action: 'Annuler la dépense',
    })
    if (ok) await agir(() => annulerDepense(depense.id, 'Annulation depuis le suivi du véhicule'), 'Dépense annulée.')
  }

  const colonnes: Colonne<Depense>[] = [
    {
      cle: 'date',
      entete: 'Date',
      valeur: (d) => d.date_depense,
      cellule: (d) => <span className="tabular-nums text-xs">{d.date_depense?.split('-').reverse().join('/')}</span>,
    },
    {
      cle: 'libelle',
      entete: 'Libellé',
      valeur: (d) => `${d.libelle} ${d.beneficiaire ?? ''}`,
      cellule: (d) => (
        <div className="min-w-0">
          <div className="truncate font-semibold text-navy-900">{d.libelle}</div>
          <div className="text-xs text-navy-400">{d.beneficiaire ?? '—'}</div>
        </div>
      ),
    },
    {
      cle: 'montant',
      entete: 'Montant',
      valeur: (d) => d.montant,
      cellule: (d) => (
        <span className={d.statut === 'annulee' ? 'tabular-nums text-navy-300 line-through' : 'font-semibold tabular-nums'}>
          {francs(d.montant)}
        </span>
      ),
    },
    {
      cle: 'statut',
      entete: 'Statut',
      valeur: (d) => d.statut,
      cellule: (d) => <Badge tone={TONS[d.statut]}>{LIBELLES[d.statut]}</Badge>,
    },
    {
      cle: 'actions',
      entete: '',
      cellule: (d) => (
        <div className="flex justify-end gap-1.5">
          {d.justificatif_url && (
            <Button size="sm" variant="secondary" title="Voir le justificatif" onClick={() => window.open(d.justificatif_url!, '_blank')}>
              <Paperclip className="h-3.5 w-3.5" />
            </Button>
          )}
          {d.statut === 'engagee' && can('finance.depenses') && (
            <Button size="sm" title="Marquer payée" onClick={() => agir(() => payerDepense(d.id), 'Règlement enregistré.')}>
              <CheckCircle2 className="h-3.5 w-3.5" />
            </Button>
          )}
          {d.statut !== 'annulee' && can('finance.depenses') && (
            <Button size="sm" variant="danger" title="Annuler" onClick={() => annuler(d)}>
              <Ban className="h-3.5 w-3.5" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  if (vehiculeEnChargement) return <Spinner />
  if (!vehicule) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to="/bus/vehicules" className="mb-2 flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          Véhicules
        </Link>
        <PageHeader
          titre={`Dépenses — ${vehicule.immatriculation}`}
          sousTitre={[vehicule.marque, vehicule.couleur, vehicule.chauffeur?.nom_complet].filter(Boolean).join(' · ') || undefined}
          icon={ReceiptText}
          actions={
            <>
              <Button variant="secondary" onClick={() => ouvrirDocument(`/bus/vehicules/${vehiculeId}/bilan/pdf`, { du: du || undefined, au: au || undefined })}>
                <FileBarChart className="h-4 w-4" />
                Bilan PDF
              </Button>
              {can('finance.depenses') && (
                <Button onClick={() => setFormOuvert(true)}>
                  <Plus className="h-4 w-4" />
                  Nouvelle dépense
                </Button>
              )}
            </>
          }
        />
      </div>

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-3">
            <StatCard label="Total de la période" value={francs(data.totaux.total)} icon={TrendingDown} accent="red" />
            <StatCard label="Payé" value={francs(data.totaux.paye)} icon={Wallet} accent="navy" />
            <StatCard label="Engagé, non réglé" value={francs(data.totaux.engage)} icon={CheckCircle2} accent="gold" hint={`${data.totaux.nombre} dépense(s)`} />
          </div>

          <DataTable
            colonnes={colonnes}
            lignes={data.depenses}
            cleLigne={(d) => d.id}
            placeholderRecherche="Rechercher une dépense…"
            messageVide="Aucune dépense enregistrée pour ce véhicule."
            largeurMin={640}
            outils={
              <div className="flex flex-wrap items-end gap-2">
                <Input label="Du" type="date" value={du} onChange={(e) => setDu(e.target.value)} />
                <Input label="Au" type="date" value={au} onChange={(e) => setAu(e.target.value)} />
              </div>
            }
          />
        </>
      )}

      {formOuvert && (
        <DepenseFormModal
          titre={`Nouvelle dépense — ${vehicule.immatriculation}`}
          vehiculeId={vehiculeId}
          onClose={() => setFormOuvert(false)}
          onEnregistre={() => {
            setFormOuvert(false)
            rafraichir()
          }}
        />
      )}
    </div>
  )
}
