import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { FileText, Landmark, Save } from 'lucide-react'
import { fetchMesBudgets, modifierNoteGestionMonBudget, type MonBudget } from '@/features/mon-espace/api'
import { francs, type StatutBudget } from '@/features/finance/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Textarea } from '@/shared/ui/Field'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { ouvrirDocument } from '@/shared/lib/download'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TONE_STATUT: Record<StatutBudget, 'green' | 'gold' | 'neutral'> = {
  actif: 'green',
  epuise: 'gold',
  annule: 'neutral',
}
const LIBELLE_STATUT: Record<StatutBudget, string> = {
  actif: 'Actif',
  epuise: 'Épuisé',
  annule: 'Clôturé',
}

/** Libre-service : mes budgets alloués, ce qu'il en reste, et comment je les gère. */
export function MonBudgetPage() {
  const { data, isLoading, isError, error } = useQuery({ queryKey: ['mon-espace-budgets'], queryFn: fetchMesBudgets })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Mon budget"
        sousTitre="Budgets qui vous sont alloués, ce qu'il en reste, et comment vous les gérez."
        icon={Landmark}
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState message={error?.message} />
      ) : data.budgets.length === 0 ? (
        <Card>
          <EmptyState label="Aucun budget ne vous a été alloué pour l'instant." />
        </Card>
      ) : (
        data.budgets.map((budget) => <BudgetCard key={budget.id} budget={budget} />)
      )}
    </div>
  )
}

function BudgetCard({ budget }: { budget: MonBudget }) {
  const queryClient = useQueryClient()
  const [note, setNote] = useState(budget.note_gestion ?? '')
  const [enregistrement, setEnregistrement] = useState(false)

  const enregistrerNote = async () => {
    setEnregistrement(true)
    try {
      await modifierNoteGestionMonBudget(budget.id, note.trim())
      succes('Note de gestion enregistrée.')
      queryClient.invalidateQueries({ queryKey: ['mon-espace-budgets'] })
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnregistrement(false)
    }
  }

  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="font-display text-base font-bold text-navy-900">{budget.libelle}</p>
          <p className="text-xs text-navy-400">
            Alloué le {new Date(budget.date_allocation).toLocaleDateString('fr-FR')}
          </p>
        </div>
        <Badge tone={TONE_STATUT[budget.statut]}>{LIBELLE_STATUT[budget.statut]}</Badge>
      </div>

      <div className="mt-3 grid grid-cols-3 gap-3">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-wide text-navy-400">Alloué</p>
          <p className="tabular-nums font-semibold text-navy-900">{francs(budget.montant_alloue)}</p>
        </div>
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-wide text-navy-400">Dépensé</p>
          <p className="tabular-nums font-semibold text-navy-900">{francs(budget.montant_depense)}</p>
        </div>
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-wide text-navy-400">Solde</p>
          <p className={budget.solde > 0 ? 'tabular-nums font-semibold text-green-600' : 'tabular-nums text-navy-300'}>
            {francs(budget.solde)}
          </p>
        </div>
      </div>

      <div className="mt-4 flex flex-col gap-1.5">
        <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
          Comment je gère ce budget
        </span>
        <Textarea
          placeholder="Expliquez comment vous comptez utiliser et suivre ce budget…"
          value={note}
          onChange={(e) => setNote(e.target.value)}
        />
      </div>

      <div className="mt-3 flex flex-wrap justify-end gap-2">
        <Button variant="secondary" onClick={() => ouvrirDocument(`/mon-espace/budgets/${budget.id}/bilan/pdf`)}>
          <FileText className="h-4 w-4" />
          Bilan PDF
        </Button>
        <Button onClick={enregistrerNote} disabled={enregistrement || note === (budget.note_gestion ?? '')}>
          <Save className="h-4 w-4" />
          Enregistrer la note
        </Button>
      </div>
    </Card>
  )
}
