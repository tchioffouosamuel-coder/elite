import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { MapPin, Pencil, Plus, Route as RouteIcon, Trash2, Users } from 'lucide-react'
import {
  creerTrajet,
  fetchTrajets,
  fetchVehicules,
  modifierTrajet,
  supprimerTrajet,
  type BusTrajet,
  type BusTrajetPayload,
} from '@/features/bus/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

export function BusTrajetsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [trajetEnEdition, setTrajetEnEdition] = useState<BusTrajet | null>(null)

  const { data: trajets, isLoading } = useQuery({ queryKey: ['bus-trajets'], queryFn: fetchTrajets })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['bus-trajets'] })

  const colonnes: Colonne<BusTrajet>[] = [
    {
      cle: 'nom',
      entete: t('bus.nom'),
      valeur: (r) => r.nom,
      cellule: (r) => (
        <Link to={`/bus/trajets/${r.id}`} className="font-semibold text-navy-900 hover:text-gold-600 hover:underline">
          {r.nom}
        </Link>
      ),
    },
    {
      cle: 'vehicule',
      entete: t('bus.vehicule'),
      valeur: (r) => r.vehicule?.immatriculation,
      cellule: (r) => r.vehicule?.immatriculation ?? '—',
    },
    {
      cle: 'arrets',
      entete: t('bus.arrets'),
      valeur: (r) => r.arrets.length,
      cellule: (r) => (
        <span className="inline-flex items-center gap-1 text-navy-600">
          <MapPin className="h-3.5 w-3.5 text-navy-300" />
          {r.arrets.length}
        </span>
      ),
      masquerMobile: true,
    },
    {
      cle: 'effectif',
      entete: t('bus.effectif'),
      valeur: (r) => r.effectif ?? 0,
      cellule: (r) => (
        <span className="inline-flex items-center gap-1 text-navy-600">
          <Users className="h-3.5 w-3.5 text-navy-300" />
          {r.effectif ?? 0}
        </span>
      ),
    },
    {
      cle: 'tarifs',
      entete: t('bus.tarifs_title'),
      cellule: (r) =>
        r.tarif_aller_retour || r.tarif_aller_simple || r.tarif_retour_simple ? (
          <span className="text-xs text-navy-500">
            {r.tarif_aller_retour ? `${t('bus.aller_retour')} ${r.tarif_aller_retour.toLocaleString('fr-FR')}` : null}
            {r.tarif_aller_simple ? ` · ${t('bus.aller_simple')} ${r.tarif_aller_simple.toLocaleString('fr-FR')}` : null}
          </span>
        ) : (
          <span className="text-navy-300">—</span>
        ),
      masquerMobile: true,
    },
    ...(can('bus.manage')
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (r: BusTrajet) => (
              <div className="flex items-center gap-1">
                <button
                  title={t('common.edit')}
                  onClick={() => {
                    setTrajetEnEdition(r)
                    setShowForm(true)
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                >
                  <Pencil className="h-4 w-4" />
                </button>
                <button
                  title={t('common.delete')}
                  onClick={async () => {
                    if (!(await confirmerSuppression(r.nom))) return
                    try {
                      await supprimerTrajet(r.id)
                      invalidate()
                      succes(t('bus.trajet_deleted'))
                    } catch (err) {
                      erreur((err as ApiError).message)
                    }
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ),
          } satisfies Colonne<BusTrajet>,
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('bus.trajets_title')}
        sousTitre={t('bus.trajets_subtitle')}
        icon={RouteIcon}
        actions={
          can('bus.manage') && (
            <Button
              onClick={() => {
                setTrajetEnEdition(null)
                setShowForm(true)
              }}
            >
              <Plus className="h-4 w-4" />
              {t('bus.trajet_add')}
            </Button>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={trajets ?? []}
          cleLigne={(r) => r.id}
          placeholderRecherche={t('bus.search_trajet')}
          messageVide={t('bus.empty_trajets')}
          largeurMin={760}
        />
      )}

      {showForm && (
        <TrajetFormModal
          trajet={trajetEnEdition}
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false)
            setTrajetEnEdition(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}

function TrajetFormModal({
  trajet,
  onClose,
  onSaved,
}: {
  trajet: BusTrajet | null
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const { data: vehicules } = useQuery({ queryKey: ['bus-vehicules', 'select'], queryFn: fetchVehicules })

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<BusTrajetPayload>({
    defaultValues: trajet
      ? {
          nom: trajet.nom,
          description: trajet.description ?? '',
          vehicule_id: trajet.vehicule?.id,
          tarif_aller_simple: trajet.tarif_aller_simple ?? undefined,
          tarif_retour_simple: trajet.tarif_retour_simple ?? undefined,
          tarif_aller_retour: trajet.tarif_aller_retour ?? undefined,
        }
      : {},
  })

  const onSubmit = async (values: BusTrajetPayload) => {
    setServerError(null)
    const payload: BusTrajetPayload = {
      ...values,
      vehicule_id: values.vehicule_id ? Number(values.vehicule_id) : null,
      tarif_aller_simple: values.tarif_aller_simple ? Number(values.tarif_aller_simple) : null,
      tarif_retour_simple: values.tarif_retour_simple ? Number(values.tarif_retour_simple) : null,
      tarif_aller_retour: values.tarif_aller_retour ? Number(values.tarif_aller_retour) : null,
    }

    try {
      if (trajet) {
        await modifierTrajet(trajet.id, payload)
        succes(t('bus.trajet_updated'))
      } else {
        await creerTrajet(payload)
        succes(t('bus.trajet_created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={trajet ? t('bus.trajet_edit') : t('bus.trajet_add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('bus.nom')}
          error={errors.nom?.message}
          {...register('nom', { required: t('bus.field_required') as string })}
        />
        <Input label={t('bus.description')} {...register('description')} />

        <Select label={t('bus.vehicule')} {...register('vehicule_id')}>
          <option value="">—</option>
          {vehicules?.map((v) => (
            <option key={v.id} value={v.id}>
              {v.immatriculation}
            </option>
          ))}
        </Select>

        <div className="rounded-xl border border-navy-100 p-3">
          <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-navy-500">{t('bus.tarifs_title')}</p>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <Input label={t('bus.aller_simple')} type="number" min={0} {...register('tarif_aller_simple')} />
            <Input label={t('bus.retour_simple')} type="number" min={0} {...register('tarif_retour_simple')} />
            <Input label={t('bus.aller_retour')} type="number" min={0} {...register('tarif_aller_retour')} />
          </div>
        </div>

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
