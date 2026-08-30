import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { Save } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { succes } from '@/shared/lib/alertes'
import {
  creerDepense,
  fetchBudgetsActifs,
  fetchComptes,
  francs,
  MODES,
  type ModePaiement,
  type RubriqueBudgetFonctionnement,
} from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

const RUBRIQUES_BUDGET: { value: RubriqueBudgetFonctionnement; label: string }[] = [
  { value: 'primes_rendement', label: 'Primes de rendement' },
  { value: 'projet_ecole', label: "Projet d'école" },
  { value: 'fenassco', label: 'FENASSCO' },
  { value: 'fonctionnement', label: 'Fonctionnement' },
  { value: 'evaluation', label: 'Évaluation' },
]

interface FormValues {
  libelle: string
  montant: number
  date_depense: string
  compte_comptable_id: number | ''
  rubrique_budget_fonctionnement: RubriqueBudgetFonctionnement | ''
  source: 'caisse' | 'revenu_personnel' | 'budget_personnel'
  budget_personnel_id: number | ''
  mode: ModePaiement
  beneficiaire: string
  reference_facture: string
  responsable: string
  statut: 'payee' | 'engagee'
}

/**
 * Saisie d'une dépense.
 *
 * Le choix « engagée / payée » est mis en avant plutôt que caché dans un
 * réglage : c'est lui qui décide si la trésorerie bouge, et se tromper fausse
 * le disponible en caisse sans qu'aucun écran ne le signale.
 */
export function DepenseFormModal({
  onClose,
  onEnregistre,
  vehiculeId,
  titre = 'Nouvelle dépense',
}: {
  onClose: () => void
  onEnregistre: () => void
  /** Rattache la dépense à ce véhicule (maintenance, entretien…) — omis pour une dépense générale. */
  vehiculeId?: number
  titre?: string
}) {
  const { t } = useTranslation()
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)
  const [justificatif, setJustificatif] = useState<File | null>(null)

  const { data: comptes } = useQuery({ queryKey: ['comptes-comptables'], queryFn: fetchComptes })

  // Seules les charges se saisissent ici : proposer les 60 comptes du plan
  // ferait choisir « Banque » comme poste de dépense.
  const charges = comptes?.filter((c) => c.classe === 6) ?? []

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm<FormValues>({
    defaultValues: {
      date_depense: new Date().toISOString().slice(0, 10),
      mode: 'especes',
      statut: 'payee',
      compte_comptable_id: '',
      rubrique_budget_fonctionnement: '',
      source: 'caisse',
      budget_personnel_id: '',
    },
  })

  const source = watch('source')
  // Chargés seulement quand la source « Budget alloué » est choisie : pas
  // besoin d'interroger les budgets pour une dépense de caisse ordinaire.
  const { data: budgets } = useQuery({
    queryKey: ['budgets-personnel', 'actifs'],
    queryFn: fetchBudgetsActifs,
    enabled: source === 'budget_personnel',
  })

  const onSubmit = async (valeurs: FormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      await creerDepense({
        libelle: valeurs.libelle,
        montant: Number(valeurs.montant),
        date_depense: valeurs.date_depense,
        compte_comptable_id: valeurs.compte_comptable_id || undefined,
        rubrique_budget_fonctionnement: valeurs.rubrique_budget_fonctionnement || undefined,
        source: valeurs.source,
        budget_personnel_id: valeurs.source === 'budget_personnel' ? valeurs.budget_personnel_id || undefined : undefined,
        mode: valeurs.mode,
        beneficiaire: valeurs.beneficiaire,
        reference_facture: valeurs.reference_facture,
        responsable: valeurs.responsable,
        statut: valeurs.statut,
        justificatif: justificatif ?? undefined,
        vehicule_id: vehiculeId,
      })

      succes(t('finance.expense_recorded'))
      onEnregistre()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) setServerError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={titre} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select label="Code comptable" {...register('compte_comptable_id')}>
          <option value="">— Achats de fournitures (par défaut)</option>
          {charges.map((c) => (
            <option key={c.id} value={c.id}>
              {c.code} · {c.libelle}
            </option>
          ))}
        </Select>

        <Select label="Rubrique du budget de fonctionnement" {...register('rubrique_budget_fonctionnement')}>
          <option value="">— Aucune —</option>
          {RUBRIQUES_BUDGET.map((r) => (
            <option key={r.value} value={r.value}>
              {r.label}
            </option>
          ))}
        </Select>

        <Select label="Source" {...register('source')}>
          <option value="caisse">Caisse</option>
          <option value="revenu_personnel">Revenu personnel</option>
          <option value="budget_personnel">Budget alloué</option>
        </Select>

        {source === 'budget_personnel' && (
          <Select
            label="Budget alloué"
            error={errors.budget_personnel_id?.message}
            {...register('budget_personnel_id', { required: 'Choisissez le budget à imputer.' })}
          >
            <option value="">—</option>
            {budgets?.map((b) => (
              <option key={b.id} value={b.id}>
                {b.personnel?.nom_complet ?? '—'} — {b.libelle} (solde : {francs(b.solde)})
              </option>
            ))}
          </Select>
        )}

        <Input
          label="Libellé"
          autoFocus
          error={errors.libelle?.message}
          {...register('libelle', { required: 'Décrivez la dépense.' })}
        />

        <div className="grid gap-3 sm:grid-cols-2">
          <Input
            label="Montant (F CFA)"
            type="number"
            min={1}
            error={errors.montant?.message}
            {...register('montant', {
              required: 'Saisissez le montant.',
              min: { value: 1, message: 'Le montant doit être supérieur à zéro.' },
            })}
          />
          <Input label="Date" type="date" {...register('date_depense')} />
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          <Select label="État" {...register('statut')}>
            <option value="payee">Payée — sort de la caisse</option>
            <option value="engagee">Engagée — pas encore réglée</option>
          </Select>
          <Select label="Mode de paiement" {...register('mode')}>
            {MODES.map((m) => (
              <option key={m.valeur} value={m.valeur}>
                {m.libelle}
              </option>
            ))}
          </Select>
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          <Input label="Bénéficiaire" placeholder="Fournisseur, prestataire…" {...register('beneficiaire')} />
          <Input label="N° de facture" placeholder="Facultatif" {...register('reference_facture')} />
        </div>

        <Input label="Responsable de l'engagement" placeholder="Facultatif" {...register('responsable')} />

        <label className="flex flex-col gap-1.5">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
            Justificatif (photo ou PDF)
          </span>
          <input
            type="file"
            accept=".jpg,.jpeg,.png,.pdf"
            onChange={(e) => setJustificatif(e.target.files?.[0] ?? null)}
            className="w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm shadow-soft file:mr-3 file:rounded-lg file:border-0 file:bg-navy-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-cream-50"
          />
        </label>

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={submitting}>
            <Save className="h-4 w-4" />
            {submitting ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
