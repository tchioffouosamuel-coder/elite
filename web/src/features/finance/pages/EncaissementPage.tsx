import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Controller, useForm } from 'react-hook-form'
import { ArrowLeft, Receipt, Wallet } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Input, MontantInput, Select, useMontantSaisie } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import { encaisser, fetchDossier, francs, ventilerAutomatiquement, MODES, type LigneVentilation, type ModePaiement } from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  montant: number
  mode: ModePaiement
  date_versement: string
  reference_externe: string
  note: string
}

/**
 * Encaissement au comptoir, en page dédiée plutôt qu'en modale : la
 * répartition par rubrique a besoin de place, et un reçu qui s'imprime juste
 * après mérite un écran à lui, pas une boîte qu'on referme dans la foulée.
 *
 * Un élève sans dossier en porte un projeté (`id` nul côté liste) : cette
 * page l'ouvre elle-même au premier accès plutôt qu'à l'affichage de la
 * caisse, qui ne doit pas créer un dossier pour chaque famille qui n'a rien
 * versé.
 */
export function EncaissementPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { eleveId } = useParams<{ eleveId: string }>()
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)
  const [allocations, setAllocations] = useState<number[]>([])
  const [allocationsModifiees, setAllocationsModifiees] = useState(false)

  const {
    data: dossier,
    isLoading,
    isError,
  } = useQuery({
    queryKey: ['dossier-scolarite', eleveId],
    queryFn: () => fetchDossier(Number(eleveId)),
    enabled: !!eleveId,
  })
  const rubriques = dossier?.rubriques ?? []

  // `values` (pas `defaultValues`) : le dossier arrive après le premier rendu
  // (fetch async), et seul `values` resynchronise le formulaire une fois la
  // donnée là — `defaultValues` figerait le montant à `undefined`.
  const {
    register,
    handleSubmit,
    watch,
    control,
    formState: { errors },
  } = useForm<FormValues>({
    values: dossier
      ? {
          montant: dossier.reste_a_payer || (undefined as unknown as number),
          mode: 'especes',
          date_versement: new Date().toISOString().slice(0, 10),
          reference_externe: '',
          note: '',
        }
      : undefined,
  })

  const montant = Number(watch('montant') || 0)
  const resteApres = (dossier?.reste_a_payer ?? 0) - montant

  // La répartition suggérée suit le montant saisi tant que l'utilisateur n'a
  // pas encore touché aux champs — dès qu'il en modifie un, on ne l'écrase
  // plus pour ne pas effacer son ajustement.
  useEffect(() => {
    if (allocationsModifiees || rubriques.length === 0) return
    setAllocations(ventilerAutomatiquement(rubriques, montant))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [montant, rubriques.length])

  const totalAlloue = useMemo(() => allocations.reduce((s, v) => s + (v || 0), 0), [allocations])
  const ventilationValide = rubriques.length === 0 || (montant > 0 && totalAlloue === montant)

  const retour = () => navigate('/caisse')

  const onSubmit = async (valeurs: FormValues) => {
    if (!dossier) return
    setServerError(null)

    if (!ventilationValide) {
      setServerError(`La répartition (${francs(totalAlloue)}) doit correspondre au montant reçu (${francs(montant)}).`)
      return
    }

    setSubmitting(true)
    try {
      const lignes: LigneVentilation[] | undefined =
        rubriques.length > 0
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
      retour()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) setServerError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  if (isLoading || !dossier) return <Spinner />
  if (isError) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <button
          onClick={retour}
          className="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
        >
          <ArrowLeft className="h-4 w-4" />
          Retour à la caisse
        </button>
        <PageHeader titre={`Encaisser — ${dossier.eleve.nom_complet}`} icon={Wallet} />
      </div>

      <Card className="max-w-2xl p-5">
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

          <Controller
            name="montant"
            control={control}
            rules={{
              required: 'Saisissez le montant reçu.',
              min: { value: 1, message: 'Le montant doit être supérieur à zéro.' },
            }}
            render={({ field }) => (
              <MontantInput
                label="Montant reçu (F CFA)"
                autoFocus
                error={errors.montant?.message}
                value={field.value}
                onChange={field.onChange}
                onBlur={field.onBlur}
              />
            )}
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

          {rubriques.length > 0 && (
            <div className="flex flex-col gap-2">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
                Répartition par rubrique
              </span>

              <div className="overflow-x-auto rounded-xl border border-navy-100">
                <table className="w-full min-w-[440px] text-xs">
                  <thead className="bg-cream-50 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                    <tr>
                      <th className="px-2.5 py-2 text-left">Rubrique</th>
                      <th className="px-2.5 py-2 text-right">Payé</th>
                      <th className="px-2.5 py-2 text-right">Reste</th>
                      <th className="px-2.5 py-2 text-right">Montant alloué</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-navy-50">
                    {rubriques.map((r, i) => (
                      <tr key={`${r.cle}:${r.dossier_frais_annexe_id ?? ''}`}>
                        <td className="px-2.5 py-1.5 font-medium text-navy-800">{r.libelle}</td>
                        <td className="px-2.5 py-1.5 text-right tabular-nums text-green-600">{francs(r.montant_paye)}</td>
                        <td className="px-2.5 py-1.5 text-right tabular-nums text-red-500">{francs(r.reste)}</td>
                        <td className="px-2.5 py-1.5 text-right">
                          <MontantAlloueInput
                            valeur={allocations[i] ?? 0}
                            onChange={(v) => {
                              setAllocationsModifiees(true)
                              setAllocations((a) => {
                                const suivant = [...a]
                                suivant[i] = v
                                return suivant
                              })
                            }}
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
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
                </table>
              </div>

              {totalAlloue !== montant && (
                <p className="text-xs font-semibold text-red-500">
                  La répartition ({francs(totalAlloue)}) doit correspondre au montant reçu.
                </p>
              )}
            </div>
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
            <Button type="button" variant="secondary" onClick={retour}>
              Annuler
            </Button>
            <Button type="submit" disabled={submitting || !ventilationValide}>
              <Wallet className="h-4 w-4" />
              {submitting ? 'Enregistrement…' : 'Encaisser'}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  )
}

/** Montant alloué à une rubrique dans le tableau de répartition : milliers groupés, en composant à part pour que chaque ligne porte son propre état de saisie. */
function MontantAlloueInput({ valeur, onChange }: { valeur: number; onChange: (valeur: number) => void }) {
  const champ = useMontantSaisie(valeur, onChange)
  return (
    <input
      {...champ}
      className="w-24 rounded-lg border border-navy-200 px-1.5 py-1 text-right text-xs tabular-nums shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
    />
  )
}
