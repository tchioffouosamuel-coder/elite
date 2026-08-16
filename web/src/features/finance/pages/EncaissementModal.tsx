import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Receipt, Wallet } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import { encaisser, francs, MODES, type DossierScolarite, type ModePaiement } from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  montant: number
  mode: ModePaiement
  date_versement: string
  reference_externe: string
  note: string
}

/**
 * Encaissement au comptoir.
 *
 * Le reçu s'ouvre juste après l'enregistrement plutôt que d'attendre une
 * seconde action : au guichet, la famille repart avec son ticket, et un reçu
 * qu'il faut penser à imprimer est un reçu qu'on oublie d'imprimer.
 */
export function EncaissementModal({
  dossier,
  onClose,
  onEncaisse,
}: {
  dossier: DossierScolarite
  onClose: () => void
  onEncaisse: () => void
}) {
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm<FormValues>({
    defaultValues: {
      montant: dossier.reste_a_payer || undefined,
      mode: 'especes',
      date_versement: new Date().toISOString().slice(0, 10),
    },
  })

  const montant = Number(watch('montant') || 0)
  const resteApres = dossier.reste_a_payer - montant

  const onSubmit = async (valeurs: FormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      const { versement_id, numero_recu } = await encaisser(dossier.id, {
        montant: Number(valeurs.montant),
        mode: valeurs.mode,
        date_versement: valeurs.date_versement || undefined,
        reference_externe: valeurs.reference_externe || undefined,
        note: valeurs.note || undefined,
      })

      succes(`Reçu ${numero_recu} enregistré.`)
      ouvrirDocument(`/versements/${versement_id}/recu`)
      onEncaisse()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) setServerError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={`Encaisser — ${dossier.eleve.nom_complet}`} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <dl className="grid grid-cols-3 gap-2 rounded-xl bg-cream-100 p-3 text-center">
          {[
            ['Total dû', francs(dossier.total_du), 'text-navy-700'],
            ['Déjà versé', francs(dossier.total_paye), 'text-green-600'],
            ['Reste', francs(dossier.reste_a_payer), 'text-red-500'],
          ].map(([libelle, valeur, couleur]) => (
            <div key={libelle}>
              <dt className="text-[11px] uppercase tracking-wide text-navy-400">{libelle}</dt>
              <dd className={`text-sm font-bold tabular-nums ${couleur}`}>{valeur}</dd>
            </div>
          ))}
        </dl>

        <Input
          label="Montant reçu (F CFA)"
          type="number"
          min={1}
          autoFocus
          error={errors.montant?.message}
          {...register('montant', {
            required: 'Saisissez le montant reçu.',
            min: { value: 1, message: 'Le montant doit être supérieur à zéro.' },
          })}
        />

        {montant > 0 && (
          <p className="-mt-2 text-xs text-navy-500">
            {resteApres > 0 ? (
              <>Restera à payer : <span className="font-semibold">{francs(resteApres)}</span></>
            ) : resteApres === 0 ? (
              <span className="font-semibold text-green-600">Le dossier sera soldé.</span>
            ) : (
              <span className="font-semibold text-blue-600">
                Trop-perçu de {francs(-resteApres)} — enregistré en avance.
              </span>
            )}
          </p>
        )}

        <div className="grid gap-3 sm:grid-cols-2">
          <Select label="Mode de paiement" {...register('mode')}>
            {MODES.map((m) => (
              <option key={m.valeur} value={m.valeur}>
                {m.libelle}
              </option>
            ))}
          </Select>
          <Input label="Date" type="date" {...register('date_versement')} />
        </div>

        <Input
          label="Référence (Mobile Money, chèque…)"
          placeholder="Facultatif"
          {...register('reference_externe')}
        />
        <Input label="Note" placeholder="Facultatif" {...register('note')} />

        <p className="flex items-start gap-2 rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
          <Receipt className="mt-0.5 h-3.5 w-3.5 flex-none" />
          Le reçu s'ouvrira automatiquement à l'enregistrement, prêt à imprimer.
        </p>

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={submitting}>
            <Wallet className="h-4 w-4" />
            {submitting ? 'Enregistrement…' : 'Encaisser'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
