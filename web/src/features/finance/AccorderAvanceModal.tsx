import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { accorderAvance, fetchPlafondAvance, francs } from '@/features/finance/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, FieldWrapper } from '@/shared/ui/Field'
import { succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface FormAccorder {
  personnel_id: number
  montant: number
  mensualite: number
  mois_debut_remboursement: string
  date_avance: string
  motif: string
}

/**
 * Accorder une avance sur salaire. Ouvert depuis le registre des avances, où
 * l'employé reste à choisir, mais aussi depuis la fiche d'un agent : `personnel`
 * est alors fourni et le sélecteur cède la place au nom déjà connu.
 */
export function AccorderAvanceModal({
  personnel,
  onClose,
  onSaved,
}: {
  personnel?: { id: number; nom_complet: string }
  onClose: () => void
  onSaved: () => void
}) {
  const [serverError, setServerError] = useState<string | null>(null)
  const { data: personnels } = useQuery({
    queryKey: ['personnels', 'avances'],
    queryFn: () => fetchPersonnels({ per_page: 500 }),
    enabled: !personnel,
  })

  const {
    register,
    handleSubmit,
    watch,
    formState: { isSubmitting, errors },
  } = useForm<FormAccorder>({
    defaultValues: {
      date_avance: new Date().toISOString().slice(0, 10),
      mois_debut_remboursement: new Date().toISOString().slice(0, 10),
      ...(personnel ? { personnel_id: personnel.id } : {}),
    },
  })

  const personnelId = personnel ? personnel.id : Number(watch('personnel_id')) || 0
  const montant = Number(watch('montant')) || 0
  const mensualite = Number(watch('mensualite')) || 0
  const nombreMois = mensualite > 0 ? Math.ceil(montant / mensualite) : 0

  // Le plafond dépend de l'agent choisi : on le charge dès la sélection pour
  // que l'échéancier se corrige dans le formulaire, pas après un refus 422.
  const { data: plafond } = useQuery({
    queryKey: ['avance-plafond', personnelId],
    queryFn: () => fetchPlafondAvance(personnelId),
    enabled: personnelId > 0,
  })

  const horsPlafond = plafond?.plafond_mensualite != null && mensualite > plafond.plafond_mensualite

  const onSubmit = async (values: FormAccorder) => {
    setServerError(null)
    try {
      await accorderAvance({
        personnel_id: personnel ? personnel.id : Number(values.personnel_id),
        montant: Number(values.montant),
        mensualite: Number(values.mensualite),
        mois_debut_remboursement: values.mois_debut_remboursement || null,
        date_avance: values.date_avance,
        motif: values.motif || null,
      })
      succes('Avance accordée.')
      onSaved()
    } catch (e) {
      setServerError((e as ApiError).message)
    }
  }

  return (
    <Modal title="Accorder une avance" onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        {personnel ? (
          <FieldWrapper label="Employé">
            <p className="rounded-xl border border-navy-100 bg-cream-50 px-3.5 py-2.5 text-sm font-semibold text-navy-800">
              {personnel.nom_complet}
            </p>
          </FieldWrapper>
        ) : (
          <Select
            label="Employé"
            error={errors.personnel_id?.message}
            {...register('personnel_id', { required: 'Choisissez un employé.' })}
          >
            <option value="">—</option>
            {personnels?.map((p) => (
              <option key={p.id} value={p.id}>
                {p.nom_complet}
              </option>
            ))}
          </Select>
        )}

        <div className="grid grid-cols-2 gap-3">
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
          <Input
            label="Mensualité (F CFA)"
            type="number"
            min={1}
            error={errors.mensualite?.message}
            {...register('mensualite', {
              required: 'Saisissez la mensualité.',
              min: { value: 1, message: 'La mensualité doit être supérieure à zéro.' },
            })}
          />
        </div>

        {/* L'échéancier n'est pas forcé uniforme : c'est l'employé qui choisit
            la mensualité, la durée s'en déduit et la dernière échéance solde
            simplement ce qui reste. */}
        {personnelId > 0 && plafond?.plafond_mensualite == null && (
          <p className="rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-800">
            Aucune rémunération n'est enregistrée pour cet employé : le plafond de remboursement ne peut pas être calculé
            et l'avance sera refusée.
          </p>
        )}

        {montant > 0 && mensualite > 0 && (
          <div
            className={
              horsPlafond
                ? 'rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700'
                : 'rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-500'
            }
          >
            <p>
              Retenue mensuelle sur salaire :{' '}
              <span className={horsPlafond ? 'font-semibold' : 'font-semibold text-navy-800'}>{francs(mensualite)}</span>{' '}
              pendant {nombreMois} mois.
            </p>
            {plafond?.plafond_mensualite != null && (
              <p className="mt-0.5">
                Salaire brut {francs(plafond.salaire_brut ?? 0)} — plafond 50% :{' '}
                <span className="font-semibold">{francs(plafond.plafond_mensualite)}</span>/mois.
              </p>
            )}
            {horsPlafond && (
              <p className="mt-0.5 font-semibold">Au-delà du plafond : réduisez la mensualité.</p>
            )}
          </div>
        )}

        <div className="grid grid-cols-2 gap-3">
          <Input label="Date de l'avance" type="date" {...register('date_avance', { required: true })} />
          <Input
            label="Début du remboursement"
            type="date"
            {...register('mois_debut_remboursement', { required: 'Requis.' })}
            error={errors.mois_debut_remboursement?.message}
          />
        </div>
        <Input label="Motif" placeholder="Facultatif" {...register('motif')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting || horsPlafond}>
            Accorder
          </Button>
        </div>
      </form>
    </Modal>
  )
}
