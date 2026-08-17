import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { ArrowLeft, Bell, Clock, MapPin, Pencil, Plus, Trash2, UserPlus } from 'lucide-react'
import {
  affecterEleve,
  ajouterArret,
  fetchTrajet,
  modifierArret,
  notifierParents,
  retirerAffectation,
  supprimerArret,
  type BusArret,
  type BusArretPayload,
  type BusTrajetDetail,
  type OptionTrajet,
  type TypeNotificationBus,
} from '@/features/bus/api'
import { fetchEleves, type Eleve } from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

export function BusTrajetDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const trajetId = Number(id)
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const [showArretForm, setShowArretForm] = useState(false)
  const [arretEnEdition, setArretEnEdition] = useState<BusArret | null>(null)
  const [showAffectationForm, setShowAffectationForm] = useState(false)
  const [showNotifyForm, setShowNotifyForm] = useState(false)

  const { data: trajet, isLoading } = useQuery({
    queryKey: ['bus-trajet', trajetId],
    queryFn: () => fetchTrajet(trajetId),
    enabled: Number.isFinite(trajetId),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['bus-trajet', trajetId] })

  if (isLoading || !trajet) {
    return <Spinner />
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <button
          onClick={() => navigate('/bus/trajets')}
          className="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
        >
          <ArrowLeft className="h-4 w-4" />
          {t('bus.back_to_trajets')}
        </button>
        <PageHeader
          titre={trajet.nom}
          sousTitre={trajet.vehicule ? `${t('bus.vehicule')} : ${trajet.vehicule.immatriculation}` : t('bus.no_vehicule')}
          actions={
            can('bus.manage') &&
            trajet.affectations.length > 0 && (
              <Button variant="secondary" onClick={() => setShowNotifyForm(true)}>
                <Bell className="h-4 w-4" />
                {t('bus.notify_parents')}
              </Button>
            )
          }
        />
      </div>

      <section className="rounded-2xl border border-navy-100 bg-white p-5 shadow-soft">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-navy-500">{t('bus.arrets')}</h2>
          {can('bus.manage') && (
            <Button
              size="sm"
              onClick={() => {
                setArretEnEdition(null)
                setShowArretForm(true)
              }}
            >
              <Plus className="h-4 w-4" />
              {t('bus.arret_add')}
            </Button>
          )}
        </div>

        {trajet.arrets.length === 0 ? (
          <EmptyState label={t('bus.empty_arrets')} />
        ) : (
          <ol className="flex flex-col gap-2">
            {trajet.arrets.map((arret) => (
              <li
                key={arret.id}
                className="flex items-center justify-between gap-3 rounded-xl border border-navy-100 bg-cream-50 px-4 py-2.5"
              >
                <div className="flex items-center gap-3">
                  <span className="flex h-7 w-7 flex-none items-center justify-center rounded-full bg-navy-800 text-xs font-semibold text-cream-50">
                    {arret.ordre}
                  </span>
                  <div>
                    <p className="text-sm font-semibold text-navy-900">{arret.nom}</p>
                    {arret.heure_passage && (
                      <p className="flex items-center gap-1 text-xs text-navy-400">
                        <Clock className="h-3 w-3" />
                        {arret.heure_passage}
                      </p>
                    )}
                  </div>
                </div>
                {can('bus.manage') && (
                  <div className="flex items-center gap-1">
                    <button
                      title={t('common.edit')}
                      onClick={() => {
                        setArretEnEdition(arret)
                        setShowArretForm(true)
                      }}
                      className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-white hover:text-navy-700"
                    >
                      <Pencil className="h-4 w-4" />
                    </button>
                    <button
                      title={t('common.delete')}
                      onClick={async () => {
                        if (!(await confirmerSuppression(arret.nom))) return
                        try {
                          await supprimerArret(trajetId, arret.id)
                          invalidate()
                          succes(t('bus.arret_deleted'))
                        } catch (err) {
                          erreur((err as ApiError).message)
                        }
                      }}
                      className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-white hover:text-red-600"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                )}
              </li>
            ))}
          </ol>
        )}
      </section>

      <section className="rounded-2xl border border-navy-100 bg-white p-5 shadow-soft">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-navy-500">{t('bus.affectations')}</h2>
          {can('bus.manage') && (
            <Button size="sm" onClick={() => setShowAffectationForm(true)}>
              <UserPlus className="h-4 w-4" />
              {t('bus.affectation_add')}
            </Button>
          )}
        </div>

        {trajet.affectations.length === 0 ? (
          <EmptyState label={t('bus.empty_affectations')} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[560px] text-sm">
              <thead>
                <tr className="border-b border-navy-100 text-left text-xs font-semibold uppercase tracking-wide text-navy-400">
                  <th className="py-2">{t('bus.eleve')}</th>
                  <th className="py-2">{t('bus.arret_select')}</th>
                  <th className="py-2">{t('bus.option_trajet')}</th>
                  <th className="py-2">{t('bus.tarif_mensuel')}</th>
                  <th className="py-2">{t('bus.statut')}</th>
                  {can('bus.manage') && <th className="py-2 text-right">{t('common.actions')}</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-navy-50">
                {trajet.affectations.map((a) => (
                  <tr key={a.id}>
                    <td className="py-2.5 font-medium text-navy-900">{a.eleve.nom_complet}</td>
                    <td className="py-2.5 text-navy-600">
                      {a.arret ? (
                        <span className="inline-flex items-center gap-1">
                          <MapPin className="h-3.5 w-3.5 text-navy-300" />
                          {a.arret.nom}
                        </span>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td className="py-2.5 text-navy-600">{t(`bus.${a.option_trajet}`)}</td>
                    <td className="py-2.5 tabular-nums text-navy-600">
                      {a.tarif_mensuel ? `${a.tarif_mensuel.toLocaleString('fr-FR')} FCFA` : '—'}
                    </td>
                    <td className="py-2.5">
                      <Badge tone={a.statut === 'actif' ? 'green' : 'neutral'}>{t(`bus.${a.statut}`)}</Badge>
                    </td>
                    {can('bus.manage') && (
                      <td className="py-2.5 text-right">
                        <button
                          title={t('bus.affectation_remove')}
                          onClick={async () => {
                            if (!(await confirmerSuppression(a.eleve.nom_complet))) return
                            try {
                              await retirerAffectation(a.id)
                              invalidate()
                              succes(t('bus.affectation_deleted'))
                            } catch (err) {
                              erreur((err as ApiError).message)
                            }
                          }}
                          className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {showArretForm && (
        <ArretFormModal
          trajetId={trajetId}
          arret={arretEnEdition}
          onClose={() => setShowArretForm(false)}
          onSaved={() => {
            setShowArretForm(false)
            setArretEnEdition(null)
            invalidate()
          }}
        />
      )}

      {showAffectationForm && (
        <AffectationFormModal
          trajet={trajet}
          onClose={() => setShowAffectationForm(false)}
          onSaved={() => {
            setShowAffectationForm(false)
            invalidate()
          }}
        />
      )}

      {showNotifyForm && <NotifyFormModal trajetId={trajetId} onClose={() => setShowNotifyForm(false)} />}
    </div>
  )
}

function NotifyFormModal({ trajetId, onClose }: { trajetId: number; onClose: () => void }) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<{ type: TypeNotificationBus; detail: string }>({ defaultValues: { type: 'retard' } })

  const onSubmit = async (values: { type: TypeNotificationBus; detail: string }) => {
    setServerError(null)
    try {
      const { envoyes } = await notifierParents(trajetId, values)
      succes(t('bus.notify_sent', { count: envoyes }))
      onClose()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={t('bus.notify_title')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select label={t('bus.notify_type')} {...register('type', { required: true })}>
          <option value="retard">{t('bus.notify_type_retard')}</option>
          <option value="incident">{t('bus.notify_type_incident')}</option>
          <option value="changement_itineraire">{t('bus.notify_type_changement_itineraire')}</option>
          <option value="autre">{t('bus.notify_type_autre')}</option>
        </Select>

        <Input
          label={t('bus.notify_detail')}
          placeholder={t('bus.notify_detail_placeholder') as string}
          error={errors.detail?.message}
          {...register('detail', { required: t('bus.field_required') as string, maxLength: 300 })}
        />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {t('bus.notify_send')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function ArretFormModal({
  trajetId,
  arret,
  onClose,
  onSaved,
}: {
  trajetId: number
  arret: BusArret | null
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<BusArretPayload>({
    defaultValues: arret
      ? { nom: arret.nom, ordre: arret.ordre, heure_passage: arret.heure_passage ?? '' }
      : { ordre: 1 },
  })

  const onSubmit = async (values: BusArretPayload) => {
    setServerError(null)
    const payload: BusArretPayload = {
      ...values,
      ordre: values.ordre ? Number(values.ordre) : null,
      heure_passage: values.heure_passage || null,
    }

    try {
      if (arret) {
        await modifierArret(trajetId, arret.id, payload)
        succes(t('bus.arret_updated'))
      } else {
        await ajouterArret(trajetId, payload)
        succes(t('bus.arret_created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={arret ? t('bus.arret_edit') : t('bus.arret_add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('bus.nom')}
          error={errors.nom?.message}
          {...register('nom', { required: t('bus.field_required') as string })}
        />
        <Input label={t('bus.ordre')} type="number" min={1} {...register('ordre')} />
        <Input label={t('bus.heure_passage')} type="time" {...register('heure_passage')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function AffectationFormModal({
  trajet,
  onClose,
  onSaved,
}: {
  trajet: BusTrajetDetail
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const [rechercheEleve, setRechercheEleve] = useState('')

  const { data: eleves } = useQuery({
    queryKey: ['eleves', 'bus-affectation', rechercheEleve],
    queryFn: () => fetchEleves({ search: rechercheEleve || undefined, per_page: 30 }),
  })

  const elevesDejaAffectes = useMemo(() => new Set(trajet.affectations.map((a) => a.eleve.id)), [trajet.affectations])

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<{ eleve_id: number; arret_id?: number; tarif_mensuel?: number; option_trajet: OptionTrajet }>({
    defaultValues: { option_trajet: 'aller_retour' },
  })

  const onSubmit = async (values: {
    eleve_id: number
    arret_id?: number
    tarif_mensuel?: number
    option_trajet: OptionTrajet
  }) => {
    setServerError(null)
    try {
      await affecterEleve({
        eleve_id: Number(values.eleve_id),
        trajet_id: trajet.id,
        arret_id: values.arret_id ? Number(values.arret_id) : null,
        tarif_mensuel: values.tarif_mensuel ? Number(values.tarif_mensuel) : null,
        option_trajet: values.option_trajet,
      })
      succes(t('bus.affectation_created'))
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={t('bus.affectation_add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('bus.search_eleve')}
          value={rechercheEleve}
          onChange={(e) => setRechercheEleve(e.target.value)}
        />

        <Select label={t('bus.eleve')} error={errors.eleve_id?.message} {...register('eleve_id', { required: t('bus.field_required') as string })}>
          <option value="">—</option>
          {eleves?.items
            .filter((e: Eleve) => !elevesDejaAffectes.has(e.id))
            .map((e: Eleve) => (
              <option key={e.id} value={e.id}>
                {e.nom_complet}
              </option>
            ))}
        </Select>

        <Select label={t('bus.arret_select')} {...register('arret_id')}>
          <option value="">—</option>
          {trajet.arrets.map((arret) => (
            <option key={arret.id} value={arret.id}>
              {arret.nom}
            </option>
          ))}
        </Select>

        <Select label={t('bus.option_trajet')} {...register('option_trajet', { required: true })}>
          <option value="aller_retour">{t('bus.aller_retour')}</option>
          <option value="aller_simple">{t('bus.aller_simple')}</option>
          <option value="retour_simple">{t('bus.retour_simple')}</option>
        </Select>

        <Input label={t('bus.tarif_mensuel')} type="number" min={0} {...register('tarif_mensuel')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
