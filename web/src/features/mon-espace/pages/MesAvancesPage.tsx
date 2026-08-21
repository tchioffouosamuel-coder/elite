import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { HandCoins, Plus } from 'lucide-react'
import {
  fetchMesAvances,
  soumettreDemandeAvance,
  type MaDemandeAvance,
  type MonAvance,
} from '@/features/mon-espace/api'
import { francs, type PlafondAvance, type StatutAvance, type StatutDemandeAvance } from '@/features/finance/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input, Textarea } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TONE_AVANCE: Record<StatutAvance, 'green' | 'gold' | 'red' | 'neutral'> = {
  en_cours: 'gold',
  partielle: 'gold',
  remboursee: 'green',
  annulee: 'neutral',
}
const LIBELLE_AVANCE: Record<StatutAvance, string> = {
  en_cours: 'En cours',
  partielle: 'Partielle',
  remboursee: 'Remboursée',
  annulee: 'Annulée',
}
const TONE_DEMANDE: Record<StatutDemandeAvance, 'green' | 'gold' | 'red'> = {
  en_attente: 'gold',
  validee: 'green',
  rejetee: 'red',
}
const LIBELLE_DEMANDE: Record<StatutDemandeAvance, string> = {
  en_attente: 'En attente',
  validee: 'Validée',
  rejetee: 'Rejetée',
}

/** Libre-service : mes avances déjà accordées, et la possibilité d'en demander une nouvelle. */
export function MesAvancesPage() {
  const queryClient = useQueryClient()
  const [demandeOuverte, setDemandeOuverte] = useState(false)

  const { data, isLoading, isError, error } = useQuery({ queryKey: ['mon-espace-avances'], queryFn: fetchMesAvances })

  const demandeEnAttente = data?.demandes.find((d) => d.statut === 'en_attente')

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Mes avances sur salaire"
        sousTitre="Avances déjà accordées et suivi de vos demandes."
        icon={HandCoins}
        actions={
          !demandeEnAttente && (
            <Button onClick={() => setDemandeOuverte(true)}>
              <Plus className="h-4 w-4" />
              Demander une avance
            </Button>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState message={error?.message} />
      ) : (
        <>
          {data.plafond.plafond_mensualite !== null && (
            <p className="rounded-xl bg-cream-100 px-3.5 py-2.5 text-sm text-navy-500">
              Salaire brut de référence <span className="font-semibold text-navy-800">{francs(data.plafond.salaire_brut ?? 0)}</span> —
              la retenue mensuelle d'une avance ne peut dépasser{' '}
              <span className="font-semibold text-navy-800">{francs(data.plafond.plafond_mensualite)}</span> par mois (50%).
            </p>
          )}

          {demandeEnAttente && (
            <p className="rounded-xl bg-gold-50 px-3.5 py-2.5 text-sm text-gold-800">
              Votre demande de {francs(demandeEnAttente.montant)} sur {demandeEnAttente.nombre_mois} mois est en attente de
              validation par l'établissement.
            </p>
          )}

          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Avances accordées</h2>
            {data.avances.length === 0 ? (
              <EmptyState label="Aucune avance accordée pour l'instant." />
            ) : (
              <div className="flex flex-col divide-y divide-navy-50">
                {data.avances.map((a: MonAvance) => (
                  <div key={a.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div>
                      <p className="text-sm font-semibold text-navy-800">
                        {francs(a.montant)} — {new Date(a.date_avance).toLocaleDateString('fr-FR')}
                      </p>
                      <p className="text-xs text-navy-400">
                        {a.nombre_mois ? `${a.nombre_mois} mois · ${francs(a.mensualite ?? 0)}/mois` : 'Échéancier non défini'}
                        {a.motif ? ` · ${a.motif}` : ''}
                      </p>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className={a.solde > 0 ? 'text-sm font-semibold tabular-nums text-red-500' : 'text-sm tabular-nums text-navy-300'}>
                        {a.solde > 0 ? `Reste ${francs(a.solde)}` : 'Soldée'}
                      </span>
                      <Badge tone={TONE_AVANCE[a.statut]}>{LIBELLE_AVANCE[a.statut]}</Badge>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </Card>

          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Mes demandes</h2>
            {data.demandes.length === 0 ? (
              <EmptyState label="Aucune demande transmise pour l'instant." />
            ) : (
              <div className="flex flex-col divide-y divide-navy-50">
                {data.demandes.map((d: MaDemandeAvance) => (
                  <div key={d.id} className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <p className="text-sm font-semibold text-navy-800">
                        {francs(d.montant)} sur {d.nombre_mois} mois — {new Date(d.created_at).toLocaleDateString('fr-FR')}
                      </p>
                      <Badge tone={TONE_DEMANDE[d.statut]}>{LIBELLE_DEMANDE[d.statut]}</Badge>
                    </div>
                    {d.statut === 'rejetee' && d.motif_rejet && (
                      <p className="text-xs text-red-600">Motif du rejet : {d.motif_rejet}</p>
                    )}
                  </div>
                ))}
              </div>
            )}
          </Card>
        </>
      )}

      {demandeOuverte && (
        <DemanderAvanceModal
          plafond={data?.plafond ?? { salaire_brut: null, plafond_mensualite: null }}
          onClose={() => setDemandeOuverte(false)}
          onSaved={() => {
            setDemandeOuverte(false)
            queryClient.invalidateQueries({ queryKey: ['mon-espace-avances'] })
          }}
        />
      )}
    </div>
  )
}

interface FormDemande {
  montant: number
  nombre_mois: number
  motif: string
}

function DemanderAvanceModal({
  plafond,
  onClose,
  onSaved,
}: {
  plafond: PlafondAvance
  onClose: () => void
  onSaved: () => void
}) {
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    formState: { isSubmitting, errors },
  } = useForm<FormDemande>({ defaultValues: { nombre_mois: 1 } })

  const montant = Number(watch('montant')) || 0
  const nombreMois = Number(watch('nombre_mois')) || 1
  const mensualite = nombreMois > 0 ? Math.ceil(montant / nombreMois) : 0
  const horsPlafond = plafond.plafond_mensualite !== null && mensualite > plafond.plafond_mensualite

  const onSubmit = async (values: FormDemande) => {
    setServerError(null)
    try {
      await soumettreDemandeAvance({
        montant: Number(values.montant),
        nombre_mois: Number(values.nombre_mois),
        motif: values.motif || null,
      })
      succes("Demande transmise, en attente de validation par l'établissement.")
      onSaved()
    } catch (e) {
      const err = e as ApiError
      setServerError(err.message)
      erreur(err.message)
    }
  }

  return (
    <Modal title="Demander une avance" onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label="Montant (F CFA)"
          type="number"
          min={1}
          error={errors.montant?.message}
          {...register('montant', { required: 'Saisissez le montant.', min: { value: 1, message: 'Le montant doit être supérieur à zéro.' } })}
        />

        <Input
          label="Nombre de mois de remboursement"
          type="number"
          min={1}
          max={36}
          error={errors.nombre_mois?.message}
          {...register('nombre_mois', {
            required: 'Requis.',
            min: { value: 1, message: 'Au moins 1 mois.' },
            max: { value: 36, message: '36 mois maximum.' },
          })}
        />

        {/* Calendrier de remboursement : ce qui sera retenu chaque mois, et la
            borne des 50% du brut que la retenue ne peut franchir. */}
        {montant > 0 && nombreMois > 0 && (
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
            {plafond.plafond_mensualite !== null && (
              <p className="mt-0.5">
                Plafond autorisé : <span className="font-semibold">{francs(plafond.plafond_mensualite)}</span>/mois (50% du
                salaire brut).
              </p>
            )}
            {horsPlafond && (
              <p className="mt-0.5 font-semibold">
                Au-delà du plafond : allongez la durée ou réduisez le montant demandé.
              </p>
            )}
          </div>
        )}

        <Textarea label="Motif" placeholder="Facultatif" {...register('motif')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting || horsPlafond}>
            Transmettre
          </Button>
        </div>
      </form>
    </Modal>
  )
}
