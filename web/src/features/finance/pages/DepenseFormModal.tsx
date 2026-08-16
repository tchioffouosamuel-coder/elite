import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { Save } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { succes } from '@/shared/lib/alertes'
import { creerDepense, fetchComptes, MODES, type ModePaiement } from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  libelle: string
  montant: number
  date_depense: string
  compte_comptable_id: number | ''
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
export function DepenseFormModal({ onClose, onEnregistre }: { onClose: () => void; onEnregistre: () => void }) {
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
    formState: { errors },
  } = useForm<FormValues>({
    defaultValues: {
      date_depense: new Date().toISOString().slice(0, 10),
      mode: 'especes',
      statut: 'payee',
      compte_comptable_id: '',
    },
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
        mode: valeurs.mode,
        beneficiaire: valeurs.beneficiaire,
        reference_facture: valeurs.reference_facture,
        responsable: valeurs.responsable,
        statut: valeurs.statut,
        justificatif: justificatif ?? undefined,
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
    <Modal title="Nouvelle dépense" onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
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

        <Select label="Poste comptable" {...register('compte_comptable_id')}>
          <option value="">— Achats de fournitures (par défaut)</option>
          {charges.map((c) => (
            <option key={c.id} value={c.id}>
              {c.code} · {c.libelle}
            </option>
          ))}
        </Select>

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
