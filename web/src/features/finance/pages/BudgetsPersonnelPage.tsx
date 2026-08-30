import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Banknote, FileText, PiggyBank, Plus, Users, Wallet, X } from 'lucide-react'
import {
  annulerBudget,
  fetchBudgetsPersonnel,
  francs,
  type BudgetPersonnel,
  type StatutBudget,
} from '@/features/finance/api'
import { AllouerBudgetModal } from '@/features/finance/AllouerBudgetModal'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { StatCard } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Textarea } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { ouvrirDocument } from '@/shared/lib/download'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TONE_STATUT: Record<StatutBudget, 'green' | 'gold' | 'neutral'> = {
  actif: 'green',
  epuise: 'gold',
  annule: 'neutral',
}

const LIBELLES_STATUT: Record<StatutBudget, string> = {
  actif: 'Actif',
  epuise: 'Épuisé',
  annule: 'Clôturé',
}

export function BudgetsPersonnelPage() {
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [budgetAClore, setBudgetAClore] = useState<BudgetPersonnel | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['budgets-personnel'],
    queryFn: () => fetchBudgetsPersonnel(),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['budgets-personnel'] })

  const colonnes: Colonne<BudgetPersonnel>[] = [
    {
      cle: 'personnel',
      entete: 'Employé',
      valeur: (b) => b.personnel?.nom_complet,
      cellule: (b) => (
        <div className="min-w-0">
          <div className="truncate font-semibold text-navy-900">{b.personnel?.nom_complet ?? '—'}</div>
          <div className="text-xs text-navy-400">{b.libelle}</div>
        </div>
      ),
    },
    {
      cle: 'school',
      entete: 'École',
      valeur: (b) => b.school?.name,
      cellule: (b) => <span className="text-navy-600">{b.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'alloue',
      entete: 'Alloué',
      valeur: (b) => b.montant_alloue,
      cellule: (b) => <span className="tabular-nums font-semibold">{francs(b.montant_alloue)}</span>,
    },
    {
      cle: 'depense',
      entete: 'Dépensé',
      valeur: (b) => b.montant_depense,
      cellule: (b) => <span className="tabular-nums text-navy-600">{francs(b.montant_depense)}</span>,
      masquerMobile: true,
    },
    {
      cle: 'solde',
      entete: 'Solde',
      valeur: (b) => b.solde,
      cellule: (b) => (
        <span className={b.solde > 0 ? 'font-semibold tabular-nums text-green-600' : 'tabular-nums text-navy-300'}>
          {francs(b.solde)}
        </span>
      ),
    },
    {
      cle: 'statut',
      entete: 'Statut',
      valeur: (b) => b.statut,
      cellule: (b) => <Badge tone={TONE_STATUT[b.statut]}>{LIBELLES_STATUT[b.statut]}</Badge>,
    },
    {
      cle: 'actions',
      entete: '',
      cellule: (b) => (
        <div className="flex justify-end gap-1.5">
          <Button size="sm" variant="secondary" onClick={() => ouvrirDocument(`/budgets-personnel/${b.id}/bilan/pdf`)}>
            <FileText className="h-3.5 w-3.5" />
            Bilan
          </Button>
          {b.statut !== 'annule' && (
            <Button size="sm" variant="danger" onClick={() => setBudgetAClore(b)}>
              <X className="h-3.5 w-3.5" />
              Clôturer
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Budgets du personnel"
        sousTitre="Enveloppes allouées au personnel et suivi de leur consommation."
        icon={PiggyBank}
        actions={
          <Button onClick={() => setShowForm(true)}>
            <Plus className="h-4 w-4" />
            Allouer un budget
          </Button>
        }
      />

      {data && (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard label="Employés concernés" value={data.totaux.effectif} icon={Users} accent="navy" />
          <StatCard label="Total alloué" value={francs(data.totaux.total_alloue)} icon={Banknote} accent="gold" />
          <StatCard label="Total dépensé" value={francs(data.totaux.total_depense)} icon={Wallet} accent="red" />
          <StatCard label="Solde disponible" value={francs(data.totaux.total_restant)} icon={PiggyBank} accent="green" />
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data?.budgets ?? []}
          cleLigne={(b) => b.id}
          placeholderRecherche="Rechercher un budget…"
          messageVide="Aucun budget alloué pour l'instant."
          largeurMin={840}
        />
      )}

      {showForm && (
        <AllouerBudgetModal
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}

      {budgetAClore && (
        <ClorerBudgetModal
          budget={budgetAClore}
          onClose={() => setBudgetAClore(null)}
          onSaved={() => {
            setBudgetAClore(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}

function ClorerBudgetModal({
  budget,
  onClose,
  onSaved,
}: {
  budget: BudgetPersonnel
  onClose: () => void
  onSaved: () => void
}) {
  const [motif, setMotif] = useState('')
  const [enCours, setEnCours] = useState(false)

  const clore = async () => {
    if (motif.trim().length < 3) return
    setEnCours(true)
    try {
      await annulerBudget(budget.id, motif.trim())
      succes('Budget clôturé.')
      onSaved()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  return (
    <Modal title={`Clôturer — ${budget.personnel?.nom_complet ?? ''}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <p className="rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
          Solde restant : <span className="font-semibold text-navy-800">{francs(budget.solde)}</span>. Les dépenses déjà
          imputées restent au registre ; le budget ne pourra simplement plus en recevoir de nouvelles.
        </p>

        <Textarea label="Motif de clôture" value={motif} onChange={(e) => setMotif(e.target.value)} />

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button variant="danger" onClick={clore} disabled={enCours || motif.trim().length < 3}>
            Confirmer la clôture
          </Button>
        </div>
      </div>
    </Modal>
  )
}
