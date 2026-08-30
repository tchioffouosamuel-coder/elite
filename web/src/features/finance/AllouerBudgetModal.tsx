import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { allouerBudget } from '@/features/finance/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, Textarea, FieldWrapper } from '@/shared/ui/Field'
import { succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface FormAllouer {
  personnel_id: number
  libelle: string
  montant_alloue: number
  date_allocation: string
  note_gestion: string
}

/**
 * Allouer un budget à un membre du personnel. Ouvert depuis le registre des
 * budgets, où l'employé reste à choisir, mais aussi depuis sa fiche : `personnel`
 * est alors fourni et le sélecteur cède la place au nom déjà connu — même
 * principe qu'`AccorderAvanceModal`.
 */
export function AllouerBudgetModal({
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
    queryKey: ['personnels', 'budgets'],
    queryFn: () => fetchPersonnels({ per_page: 500 }),
    enabled: !personnel,
  })

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<FormAllouer>({
    defaultValues: {
      date_allocation: new Date().toISOString().slice(0, 10),
      ...(personnel ? { personnel_id: personnel.id } : {}),
    },
  })

  const onSubmit = async (values: FormAllouer) => {
    setServerError(null)
    try {
      await allouerBudget({
        personnel_id: personnel ? personnel.id : Number(values.personnel_id),
        libelle: values.libelle,
        montant_alloue: Number(values.montant_alloue),
        date_allocation: values.date_allocation,
        note_gestion: values.note_gestion || null,
      })
      succes('Budget alloué.')
      onSaved()
    } catch (e) {
      setServerError((e as ApiError).message)
    }
  }

  return (
    <Modal title="Allouer un budget" onClose={onClose}>
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

        <Input
          label="Libellé du budget"
          placeholder="Fournitures de bureau, frais de mission…"
          error={errors.libelle?.message}
          {...register('libelle', { required: 'Décrivez le budget.' })}
        />

        <div className="grid grid-cols-2 gap-3">
          <Input
            label="Montant alloué (F CFA)"
            type="number"
            min={1}
            error={errors.montant_alloue?.message}
            {...register('montant_alloue', {
              required: 'Saisissez le montant.',
              min: { value: 1, message: 'Le montant doit être supérieur à zéro.' },
            })}
          />
          <Input label="Date d'allocation" type="date" {...register('date_allocation', { required: true })} />
        </div>

        <Textarea
          label="Note de gestion (facultatif)"
          placeholder="Comment ce budget sera géré — l'intéressé pourra la compléter lui-même depuis son espace."
          {...register('note_gestion')}
        />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            Allouer
          </Button>
        </div>
      </form>
    </Modal>
  )
}
