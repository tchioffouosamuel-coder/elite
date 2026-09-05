import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Controller, useForm } from 'react-hook-form'
import { accorderAvance, fetchPlafondAvance } from '@/features/finance/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, MontantInput, Select, FieldWrapper } from '@/shared/ui/Field'
import { succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
import { EcheancierTable, genererEcheancierUniforme, sommeEcheancier, type LigneEcheancier } from '@/features/finance/EcheancierAvanceEditor'

interface FormAccorder {
  personnel_id: number
  montant: number
  nombre_mois: number
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
  const [echeancier, setEcheancier] = useState<LigneEcheancier[]>([])
  const [touched, setTouched] = useState(false)
  const { data: personnels } = useQuery({
    queryKey: ['personnels', 'avances'],
    queryFn: () => fetchPersonnels({ per_page: 500 }),
    enabled: !personnel,
  })

  const {
    register,
    handleSubmit,
    watch,
    control,
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
  const nombreMois = Number(watch('nombre_mois')) || 0
  const moisDebut = watch('mois_debut_remboursement')

  // Le plafond dépend de l'agent choisi : on le charge dès la sélection pour
  // que l'échéancier se corrige dans le formulaire, pas après un refus 422.
  const { data: plafond } = useQuery({
    queryKey: ['avance-plafond', personnelId],
    queryFn: () => fetchPlafondAvance(personnelId),
    enabled: personnelId > 0,
  })

  // Régénère la répartition égale tant que l'utilisateur n'a pas corrigé une
  // ligne à la main — un changement de durée ou de date de départ reste
  // structurel et régénère toujours le tableau.
  useEffect(() => {
    if (touched) return
    setEcheancier(genererEcheancierUniforme(montant, nombreMois, moisDebut))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [montant, nombreMois, moisDebut, touched])

  const reinitialiserRepartition = () => {
    setEcheancier(genererEcheancierUniforme(montant, nombreMois, moisDebut))
    setTouched(false)
  }

  const modifierLigne = (index: number, valeur: number) => {
    setTouched(true)
    setEcheancier((lignes) => lignes.map((l, i) => (i === index ? { ...l, montant: valeur } : l)))
  }

  const totalReparti = sommeEcheancier(echeancier)
  const totalValide = echeancier.length > 0 && totalReparti === montant
  const ligneHorsPlafond =
    plafond?.plafond_mensualite != null && echeancier.some((l) => l.montant > plafond.plafond_mensualite!)

  const onSubmit = async (values: FormAccorder) => {
    setServerError(null)
    try {
      await accorderAvance({
        personnel_id: personnel ? personnel.id : Number(values.personnel_id),
        montant: Number(values.montant),
        echeancier,
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
          <Controller
            name="montant"
            control={control}
            rules={{
              required: 'Saisissez le montant.',
              min: { value: 1, message: 'Le montant doit être supérieur à zéro.' },
            }}
            render={({ field }) => (
              <MontantInput
                label="Montant (F CFA)"
                error={errors.montant?.message}
                value={field.value}
                onChange={field.onChange}
                onBlur={field.onBlur}
              />
            )}
          />
          <Input
            label="Nombre de mois"
            type="number"
            min={1}
            error={errors.nombre_mois?.message}
            {...register('nombre_mois', {
              required: 'Saisissez le nombre de mois.',
              min: { value: 1, message: 'Il faut au moins un mois.' },
            })}
          />
        </div>

        {personnelId > 0 && plafond?.plafond_mensualite == null && (
          <p className="rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-800">
            Aucune rémunération n'est enregistrée pour cet employé : le plafond de remboursement ne peut pas être calculé
            et l'avance sera refusée.
          </p>
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

        {echeancier.length > 0 && (
          <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
                Répartition du remboursement, mois par mois
              </span>
              {touched && (
                <button
                  type="button"
                  onClick={reinitialiserRepartition}
                  className="text-xs font-semibold text-navy-500 underline decoration-dotted hover:text-navy-700"
                >
                  Répartir également
                </button>
              )}
            </div>
            <EcheancierTable
              echeancier={echeancier}
              montantCible={montant}
              plafondMensualite={plafond?.plafond_mensualite ?? null}
              onChangeLigne={modifierLigne}
            />
            {plafond?.plafond_mensualite != null && (
              <p className="px-1 text-xs text-navy-400">
                Salaire brut {plafond.salaire_brut != null ? plafond.salaire_brut.toLocaleString('fr-FR') : '—'} F CFA — plafond
                50% : {plafond.plafond_mensualite.toLocaleString('fr-FR')} F CFA/mois.
              </p>
            )}
            {ligneHorsPlafond && (
              <p className="px-1 text-xs font-semibold text-red-600">
                Une ou plusieurs lignes dépassent le plafond : réduisez les montants concernés.
              </p>
            )}
          </div>
        )}

        <Input label="Motif" placeholder="Facultatif" {...register('motif')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting || !totalValide || ligneHorsPlafond}>
            Accorder
          </Button>
        </div>
      </form>
    </Modal>
  )
}
