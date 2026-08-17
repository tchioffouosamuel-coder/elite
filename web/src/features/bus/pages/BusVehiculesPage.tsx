import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Bus, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  creerVehicule,
  fetchVehicules,
  modifierVehicule,
  supprimerVehicule,
  type BusVehicule,
  type BusVehiculePayload,
} from '@/features/bus/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

export function BusVehiculesPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [vehiculeEnEdition, setVehiculeEnEdition] = useState<BusVehicule | null>(null)

  const { data: vehicules, isLoading } = useQuery({ queryKey: ['bus-vehicules'], queryFn: fetchVehicules })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['bus-vehicules'] })

  const colonnes: Colonne<BusVehicule>[] = [
    {
      cle: 'immatriculation',
      entete: t('bus.immatriculation'),
      valeur: (v) => v.immatriculation,
      cellule: (v) => <span className="font-mono font-semibold text-navy-900">{v.immatriculation}</span>,
    },
    {
      cle: 'marque',
      entete: t('bus.marque'),
      valeur: (v) => v.marque,
      cellule: (v) => v.marque ?? '—',
    },
    {
      cle: 'capacite',
      entete: t('bus.capacite'),
      valeur: (v) => v.capacite,
      cellule: (v) => <span className="tabular-nums">{v.capacite}</span>,
    },
    {
      cle: 'chauffeur',
      entete: t('bus.chauffeur'),
      valeur: (v) => v.chauffeur?.nom_complet,
      cellule: (v) => v.chauffeur?.nom_complet ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'statut',
      entete: t('bus.statut'),
      valeur: (v) => v.statut,
      cellule: (v) => <Badge tone={v.statut === 'actif' ? 'green' : 'neutral'}>{t(`bus.${v.statut}`)}</Badge>,
    },
    ...(can('bus.manage')
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (v: BusVehicule) => (
              <div className="flex items-center gap-1">
                <button
                  title={t('common.edit')}
                  onClick={() => {
                    setVehiculeEnEdition(v)
                    setShowForm(true)
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                >
                  <Pencil className="h-4 w-4" />
                </button>
                <button
                  title={t('common.delete')}
                  onClick={async () => {
                    if (!(await confirmerSuppression(v.immatriculation))) return
                    try {
                      await supprimerVehicule(v.id)
                      invalidate()
                      succes(t('bus.vehicule_deleted'))
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
          } satisfies Colonne<BusVehicule>,
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('bus.vehicules_title')}
        sousTitre={t('bus.vehicules_subtitle')}
        icon={Bus}
        actions={
          can('bus.manage') && (
            <Button
              onClick={() => {
                setVehiculeEnEdition(null)
                setShowForm(true)
              }}
            >
              <Plus className="h-4 w-4" />
              {t('bus.vehicule_add')}
            </Button>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={vehicules ?? []}
          cleLigne={(v) => v.id}
          placeholderRecherche={t('bus.search_vehicule')}
          messageVide={t('bus.empty_vehicules')}
          largeurMin={760}
        />
      )}

      {showForm && (
        <VehiculeFormModal
          vehicule={vehiculeEnEdition}
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false)
            setVehiculeEnEdition(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}

function VehiculeFormModal({
  vehicule,
  onClose,
  onSaved,
}: {
  vehicule: BusVehicule | null
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const { data: personnels } = useQuery({ queryKey: ['personnels', 'bus'], queryFn: () => fetchPersonnels({ per_page: 500 }) })

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<BusVehiculePayload>({
    defaultValues: vehicule
      ? {
          immatriculation: vehicule.immatriculation,
          marque: vehicule.marque ?? '',
          capacite: vehicule.capacite,
          chauffeur_id: vehicule.chauffeur?.id,
          statut: vehicule.statut,
        }
      : { capacite: 30, statut: 'actif' },
  })

  const onSubmit = async (values: BusVehiculePayload) => {
    setServerError(null)
    const payload: BusVehiculePayload = {
      ...values,
      capacite: values.capacite ? Number(values.capacite) : null,
      chauffeur_id: values.chauffeur_id ? Number(values.chauffeur_id) : null,
    }

    try {
      if (vehicule) {
        await modifierVehicule(vehicule.id, payload)
        succes(t('bus.vehicule_updated'))
      } else {
        await creerVehicule(payload)
        succes(t('bus.vehicule_created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={vehicule ? t('bus.vehicule_edit') : t('bus.vehicule_add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('bus.immatriculation')}
          error={errors.immatriculation?.message}
          {...register('immatriculation', { required: t('bus.field_required') as string })}
        />
        <Input label={t('bus.marque')} {...register('marque')} />
        <Input label={t('bus.capacite')} type="number" min={1} {...register('capacite')} />

        <Select label={t('bus.chauffeur')} {...register('chauffeur_id')}>
          <option value="">—</option>
          {personnels?.items.map((p) => (
            <option key={p.id} value={p.id}>
              {p.nom_complet}
            </option>
          ))}
        </Select>

        <Select label={t('bus.statut')} {...register('statut')}>
          <option value="actif">{t('bus.actif')}</option>
          <option value="hors_service">{t('bus.hors_service')}</option>
        </Select>

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
