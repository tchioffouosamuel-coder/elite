import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { ListTree, Receipt, Wallet } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Spinner } from '@/shared/ui/Feedback'
import { succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import {
  encaisser,
  fetchDossier,
  francs,
  MODES,
  type DossierScolarite,
  type LigneVentilation,
  type ModePaiement,
  type RubriqueScolarite,
} from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  montant: number
  mode: ModePaiement
  date_versement: string
  reference_externe: string
  note: string
}

/** Reproduit côté client l'ordre de ventilation automatique du serveur (report → scolarité → frais annexes → bus), pour préremplir la répartition manuelle. */
function ventilerAutomatiquement(rubriques: RubriqueScolarite[], montant: number): number[] {
  let restant = montant
  return rubriques.map((r) => {
    if (restant <= 0) return 0
    const part = Math.min(restant, r.reste)
    restant -= part
    return part
  })
}

/**
 * Encaissement au comptoir.
 *
 * Le reçu s'ouvre juste après l'enregistrement plutôt que d'attendre une
 * seconde action : au guichet, la famille repart avec son ticket, et un reçu
 * qu'il faut penser à imprimer est un reçu qu'on oublie d'imprimer.
 */
export function EncaissementModal({
  dossier: dossierResume,
  onClose,
  onEncaisse,
}: {
  dossier: DossierScolarite
  onClose: () => void
  onEncaisse: () => void
}) {
  const { t } = useTranslation()
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)
  const [repartitionManuelle, setRepartitionManuelle] = useState(false)
  const [allocations, setAllocations] = useState<number[]>([])

  // Le résumé passé par la liste n'a pas la décomposition par rubrique ;
  // on va la chercher pour cette fiche précise, sans l'alourdir pour l'écran
  // de recouvrement qui liste tout l'établissement.
  const { data: dossier = dossierResume } = useQuery({
    queryKey: ['dossier-scolarite', dossierResume.eleve.id],
    queryFn: () => fetchDossier(dossierResume.eleve.id),
    placeholderData: dossierResume,
  })
  const rubriques = dossier.rubriques ?? []

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm<FormValues>({
    defaultValues: {
      montant: dossierResume.reste_a_payer || undefined,
      mode: 'especes',
      date_versement: new Date().toISOString().slice(0, 10),
    },
  })

  const montant = Number(watch('montant') || 0)
  const resteApres = dossier.reste_a_payer - montant

  // La répartition suggérée suit le montant saisi tant que l'utilisateur n'a
  // pas encore touché aux champs — dès qu'il en modifie un, on ne l'écrase
  // plus pour ne pas effacer son ajustement.
  const [allocationsModifiees, setAllocationsModifiees] = useState(false)
  useEffect(() => {
    if (!repartitionManuelle || allocationsModifiees || rubriques.length === 0) return
    setAllocations(ventilerAutomatiquement(rubriques, montant))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [repartitionManuelle, montant, rubriques.length])

  const activerRepartitionManuelle = () => {
    setAllocationsModifiees(false)
    setAllocations(ventilerAutomatiquement(rubriques, montant))
    setRepartitionManuelle(true)
  }

  const totalAlloue = useMemo(() => allocations.reduce((s, v) => s + (v || 0), 0), [allocations])
  const ventilationValide = !repartitionManuelle || (montant > 0 && totalAlloue === montant)

  const onSubmit = async (valeurs: FormValues) => {
    setServerError(null)

    if (repartitionManuelle && !ventilationValide) {
      setServerError(`La répartition (${francs(totalAlloue)}) doit correspondre au montant reçu (${francs(montant)}).`)
      return
    }

    setSubmitting(true)
    try {
      const lignes: LigneVentilation[] | undefined = repartitionManuelle
        ? rubriques
            .map((r, i) => ({
              affectation: r.cle,
              dossier_frais_annexe_id: r.dossier_frais_annexe_id,
              libelle: r.libelle,
              montant: allocations[i] || 0,
            }))
            .filter((l) => l.montant > 0)
        : undefined

      const { versement_id, numero_recu } = await encaisser(dossier.id, {
        montant: Number(valeurs.montant),
        mode: valeurs.mode,
        date_versement: valeurs.date_versement || undefined,
        reference_externe: valeurs.reference_externe || undefined,
        note: valeurs.note || undefined,
        lignes,
      })

      succes(t('finance.receipt_recorded', { numero: numero_recu }))
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

        {rubriques.length > 0 && (
          <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
                Détail par rubrique
              </span>
              {!repartitionManuelle && (
                <button
                  type="button"
                  onClick={activerRepartitionManuelle}
                  className="flex items-center gap-1 text-xs font-semibold text-navy-500 hover:text-navy-800"
                >
                  <ListTree className="h-3.5 w-3.5" />
                  Répartir manuellement
                </button>
              )}
            </div>

            <div className="overflow-x-auto rounded-xl border border-navy-100">
              <table className="w-full min-w-[420px] text-xs">
                <thead className="bg-cream-50 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                  <tr>
                    <th className="px-2.5 py-2 text-left">Rubrique</th>
                    <th className="px-2.5 py-2 text-right">Payé</th>
                    <th className="px-2.5 py-2 text-right">Reste</th>
                    <th className="px-2.5 py-2 text-right">{repartitionManuelle ? 'Alloué' : 'Dû'}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-navy-50">
                  {rubriques.map((r, i) => (
                    <tr key={`${r.cle}:${r.dossier_frais_annexe_id ?? ''}`}>
                      <td className="px-2.5 py-1.5 font-medium text-navy-800">{r.libelle}</td>
                      <td className="px-2.5 py-1.5 text-right tabular-nums text-green-600">{francs(r.montant_paye)}</td>
                      <td className="px-2.5 py-1.5 text-right tabular-nums text-red-500">{francs(r.reste)}</td>
                      <td className="px-2.5 py-1.5 text-right">
                        {repartitionManuelle ? (
                          <input
                            type="number"
                            min={0}
                            value={allocations[i] ?? 0}
                            onChange={(e) => {
                              setAllocationsModifiees(true)
                              setAllocations((a) => {
                                const suivant = [...a]
                                suivant[i] = Number(e.target.value) || 0
                                return suivant
                              })
                            }}
                            className="w-24 rounded-lg border border-navy-200 px-1.5 py-1 text-right text-xs tabular-nums shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
                          />
                        ) : (
                          <span className="tabular-nums text-navy-700">{francs(r.montant_du)}</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
                {repartitionManuelle && (
                  <tfoot>
                    <tr className="border-t border-navy-100 bg-cream-50/70 font-semibold">
                      <td className="px-2.5 py-1.5 text-navy-700">Total réparti</td>
                      <td />
                      <td />
                      <td className={`px-2.5 py-1.5 text-right tabular-nums ${totalAlloue === montant ? 'text-green-600' : 'text-red-500'}`}>
                        {francs(totalAlloue)}
                      </td>
                    </tr>
                  </tfoot>
                )}
              </table>
            </div>
          </div>
        )}

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

        {montant > 0 && !repartitionManuelle && (
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

        {repartitionManuelle && totalAlloue !== montant && (
          <p className="-mt-2 text-xs font-semibold text-red-500">
            La répartition ({francs(totalAlloue)}) doit correspondre au montant reçu.
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
          <Button type="submit" disabled={submitting || !ventilationValide}>
            <Wallet className="h-4 w-4" />
            {submitting ? 'Enregistrement…' : 'Encaisser'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
