import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Controller, useForm } from 'react-hook-form'
import { ArrowLeft, Bus, Receipt } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Input, MontantInput, Select } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import { francs } from '@/features/finance/api'
import {
  fetchSituationPaiementBus,
  encaisserBus,
  annulerVersementBus,
  MODES_PAIEMENT_BUS,
  type ModePaiementBus,
  type MoisPaiementBus,
} from '@/features/bus/api'
import type { ApiError } from '@/shared/types/api'

const TONE_STATUT: Record<string, 'green' | 'gold' | 'red' | 'neutral'> = {
  solde: 'green',
  partiel: 'gold',
  impaye: 'red',
  sans_frais: 'neutral',
}

const LIBELLE_STATUT: Record<string, string> = {
  solde: 'Réglé',
  partiel: 'Partiel',
  impaye: 'Impayé',
  sans_frais: 'Sans frais',
}

interface FormValues {
  mois: string
  montant: number
  mode: ModePaiementBus
  date_versement: string
}

function libelleMois(mois: string): string {
  return new Date(mois).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
}

/**
 * Paiement du transport scolaire, mois par mois — un registre à part de la
 * caisse de scolarité : chaque mensualité porte son propre reçu, sans lien
 * avec le dossier annuel de l'élève.
 */
export function BusPaiementPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { affectationId } = useParams<{ affectationId: string }>()
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const { data: situation, isLoading, isError } = useQuery({
    queryKey: ['bus-paiement', affectationId],
    queryFn: () => fetchSituationPaiementBus(Number(affectationId)),
    enabled: !!affectationId,
  })

  const premierMoisImpaye = situation?.situation_mensuelle.find((m) => m.reste > 0)

  const { register, handleSubmit, control, watch, setValue } = useForm<FormValues>({
    values: premierMoisImpaye
      ? {
          mois: premierMoisImpaye.mois,
          montant: premierMoisImpaye.reste,
          mode: 'especes',
          date_versement: new Date().toISOString().slice(0, 10),
        }
      : undefined,
  })

  const moisChoisi = watch('mois')

  // Changer de mois recharge le montant suggéré (le reste dû sur ce mois),
  // sans écraser une saisie manuelle du montant lui-même.
  useEffect(() => {
    const ligne = situation?.situation_mensuelle.find((m) => m.mois === moisChoisi)
    if (ligne) setValue('montant', ligne.reste)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [moisChoisi])

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['bus-paiement', affectationId] })

  const onSubmit = async (valeurs: FormValues) => {
    if (!affectationId) return
    setServerError(null)
    setSubmitting(true)

    try {
      const { versement_id, numero_recu } = await encaisserBus(Number(affectationId), {
        mois: valeurs.mois,
        montant: Number(valeurs.montant),
        mode: valeurs.mode,
        date_versement: valeurs.date_versement || undefined,
      })

      succes(`Encaissement enregistré — reçu ${numero_recu}.`)
      ouvrirDocument(`/bus/versements/${versement_id}/recu`)
      invalider()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) setServerError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  const annulerUnVersement = async (versementId: number, numeroRecu: string) => {
    const ok = await confirmer({
      titre: `Annuler le reçu ${numeroRecu} ?`,
      message: 'Le reçu reste au registre, marqué annulé, et le mois redevient dû.',
      action: 'Annuler le reçu',
    })
    if (!ok) return

    try {
      await annulerVersementBus(versementId, 'Annulation depuis le paiement bus')
      succes('Reçu annulé.')
      invalider()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    }
  }

  if (isLoading || !situation) return <Spinner />
  if (isError) return <ErrorState />

  const { affectation } = situation

  return (
    <div className="flex flex-col gap-5">
      <div>
        <button
          onClick={() => navigate('/bus/eleves')}
          className="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
        >
          <ArrowLeft className="h-4 w-4" />
          Retour aux élèves
        </button>
        <PageHeader titre={`Bus — ${affectation.eleve.nom_complet}`} sousTitre={affectation.trajet} icon={Bus} />
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <Card className="p-5">
          <h2 className="mb-3 font-display text-sm font-bold text-navy-900">Situation mensuelle</h2>

          <dl className="mb-4 grid grid-cols-3 gap-2 rounded-xl bg-cream-100 p-3 text-center">
            {[
              ['Total dû', francs(situation.total_du), 'text-navy-700'],
              ['Déjà versé', francs(situation.total_paye), 'text-green-600'],
              ['Reste', francs(situation.reste_a_payer), 'text-red-500'],
            ].map(([libelle, valeur, couleur]) => (
              <div key={libelle}>
                <dt className="text-[11px] uppercase tracking-wide text-navy-400">{libelle}</dt>
                <dd className={`text-sm font-bold tabular-nums ${couleur}`}>{valeur}</dd>
              </div>
            ))}
          </dl>

          <div className="flex flex-col gap-1.5">
            {situation.situation_mensuelle.map((m: MoisPaiementBus) => (
              <div
                key={m.mois}
                className="flex items-center justify-between rounded-lg border border-navy-100 px-3 py-2 text-sm"
              >
                <span className="font-medium capitalize text-navy-800">{libelleMois(m.mois)}</span>
                <div className="flex items-center gap-2">
                  <span className="tabular-nums text-navy-500">{francs(m.paye)} / {francs(m.du)}</span>
                  <Badge tone={TONE_STATUT[m.statut] ?? 'neutral'}>{LIBELLE_STATUT[m.statut] ?? m.statut}</Badge>
                </div>
              </div>
            ))}
            {situation.situation_mensuelle.length === 0 && (
              <p className="rounded-xl border border-dashed border-navy-200 px-3.5 py-4 text-center text-sm text-navy-400">
                Aucun mois dû pour cette souscription.
              </p>
            )}
          </div>
        </Card>

        <Card className="p-5">
          <h2 className="mb-3 font-display text-sm font-bold text-navy-900">Encaisser un mois</h2>

          <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
            <Select label="Mois" {...register('mois', { required: true })}>
              {situation.situation_mensuelle.map((m) => (
                <option key={m.mois} value={m.mois}>
                  {libelleMois(m.mois)} — {LIBELLE_STATUT[m.statut] ?? m.statut}
                </option>
              ))}
            </Select>

            <Controller
              name="montant"
              control={control}
              rules={{ required: true, min: 1 }}
              render={({ field }) => (
                <MontantInput
                  label="Montant reçu (F CFA)"
                  value={field.value}
                  onChange={field.onChange}
                  onBlur={field.onBlur}
                />
              )}
            />

            <div className="grid gap-3 sm:grid-cols-2">
              <Select label="Mode de paiement" {...register('mode')}>
                {MODES_PAIEMENT_BUS.map((m) => (
                  <option key={m.valeur} value={m.valeur}>
                    {m.libelle}
                  </option>
                ))}
              </Select>
              <Input label="Date" type="date" {...register('date_versement')} />
            </div>

            <p className="flex items-start gap-2 rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
              <Receipt className="mt-0.5 h-3.5 w-3.5 flex-none" />
              Un reçu distinct de la scolarité s'ouvrira automatiquement, prêt à imprimer.
            </p>

            {serverError && <p className="text-sm text-red-500">{serverError}</p>}

            <div className="flex justify-end">
              <Button type="submit" disabled={submitting || situation.situation_mensuelle.length === 0}>
                <Bus className="h-4 w-4" />
                {submitting ? 'Enregistrement…' : 'Encaisser'}
              </Button>
            </div>
          </form>
        </Card>
      </div>

      <Card className="p-5">
        <h2 className="mb-3 font-display text-sm font-bold text-navy-900">Historique des versements</h2>

        {situation.versements.length === 0 ? (
          <p className="rounded-xl border border-dashed border-navy-200 px-3.5 py-4 text-center text-sm text-navy-400">
            Aucun versement enregistré.
          </p>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-navy-100">
            <table className="w-full min-w-[560px] text-sm">
              <thead className="bg-cream-50 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                <tr>
                  <th className="px-3 py-2 text-left">Reçu</th>
                  <th className="px-3 py-2 text-left">Mois</th>
                  <th className="px-3 py-2 text-left">Date</th>
                  <th className="px-3 py-2 text-right">Montant</th>
                  <th className="px-3 py-2 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-navy-50">
                {situation.versements.map((v) => (
                  <tr key={v.id} className={v.annule ? 'opacity-50' : undefined}>
                    <td className="px-3 py-2 font-medium text-navy-800">
                      {v.numero_recu}
                      {v.annule && <span className="ml-1.5 text-xs font-semibold text-red-500">(annulé)</span>}
                    </td>
                    <td className="px-3 py-2 capitalize text-navy-600">{libelleMois(v.mois)}</td>
                    <td className="px-3 py-2 text-navy-600">{new Date(v.date_versement).toLocaleDateString('fr-FR')}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{francs(v.montant)}</td>
                    <td className="px-3 py-2 text-right">
                      <div className="flex justify-end gap-2">
                        <button
                          className="text-xs font-semibold text-navy-500 hover:text-navy-800"
                          onClick={() => ouvrirDocument(`/bus/versements/${v.id}/recu`)}
                        >
                          Reçu
                        </button>
                        {!v.annule && (
                          <button
                            className="text-xs font-semibold text-red-500 hover:text-red-700"
                            onClick={() => annulerUnVersement(v.id, v.numero_recu)}
                          >
                            Annuler
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  )
}
