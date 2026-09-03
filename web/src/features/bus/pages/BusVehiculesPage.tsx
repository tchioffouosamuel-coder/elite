import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Bus, FileDown, ReceiptText, FileBarChart, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  creerVehicule,
  fetchVehicules,
  modifierVehicule,
  supprimerVehicule,
  type BusVehicule,
  type BusVehiculePayload,
} from '@/features/bus/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { fetchSchools } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { ouvrirDocument } from '@/shared/lib/download'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/** Correspondance des couleurs les plus courantes, en repli sur une pastille neutre si le texte saisi n'est pas reconnu. */
const COULEURS_CSS: Record<string, string> = {
  rouge: '#dc2626',
  bleu: '#2563eb',
  jaune: '#eab308',
  vert: '#16a34a',
  blanc: '#e5e7eb',
  noir: '#18181b',
  orange: '#ea580c',
  gris: '#6b7280',
  marron: '#78350f',
  violet: '#7c3aed',
  rose: '#db2777',
}

function couleurCss(couleur: string | null): string {
  if (!couleur) return '#d5dde5'
  return COULEURS_CSS[couleur.trim().toLowerCase()] ?? '#9ca3af'
}

export function BusVehiculesPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const navigate = useNavigate()
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
      cle: 'couleur',
      entete: t('bus.couleur'),
      valeur: (v) => v.couleur,
      cellule: (v) => (
        <span className="flex items-center gap-1.5">
          <span
            className="h-3 w-3 flex-none rounded-full ring-1 ring-navy-200/60"
            style={{ backgroundColor: couleurCss(v.couleur) }}
          />
          {v.couleur ?? '—'}
        </span>
      ),
    },
    {
      cle: 'marque',
      entete: t('bus.marque'),
      valeur: (v) => v.marque,
      cellule: (v) => v.marque ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'capacite',
      entete: t('bus.capacite'),
      valeur: (v) => v.capacite,
      cellule: (v) => <span className="tabular-nums">{v.capacite}</span>,
      masquerMobile: true,
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
    {
      cle: 'school',
      entete: t('classes.ecole'),
      valeur: (v) => v.school?.name,
      cellule: (v) => <span className="text-navy-600">{v.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (v: BusVehicule) => (
        <div className="flex items-center gap-1">
          <button
            title="Liste des élèves (PDF)"
            onClick={() => ouvrirDocument(`/bus/vehicules/${v.id}/eleves/pdf`)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <FileDown className="h-4 w-4" />
          </button>
          <button
            title="Dépenses du véhicule"
            onClick={() => navigate(`/transport/vehicules/${v.id}/depenses`)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <ReceiptText className="h-4 w-4" />
          </button>
          <button
            title="Bilan financier (PDF)"
            onClick={() => ouvrirDocument(`/bus/vehicules/${v.id}/bilan/pdf`)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <FileBarChart className="h-4 w-4" />
          </button>
          {can('bus.manage') && (
            <>
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
            </>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('bus.vehicules_title')}
        sousTitre={t('bus.vehicules_subtitle')}
        icon={Bus}
        actions={
          can('bus.manage') && (
            <div className="flex items-center gap-2">
              <ImportExportBar
                titreImport={t('bus.vehicules_title')}
                importUrl="/bus/vehicules/import"
                exportUrl="/bus/vehicules/export"
                modeleUrl="/bus/vehicules/modele"
                colonnes={['Immatriculation', 'Marque', 'Couleur', 'Capacité', 'Chauffeur', 'Statut']}
                nomFichier="vehicules-bus"
                onImported={invalidate}
              />
              <Button
                onClick={() => {
                  setVehiculeEnEdition(null)
                  setShowForm(true)
                }}
              >
                <Plus className="h-4 w-4" />
                {t('bus.vehicule_add')}
              </Button>
            </div>
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
          largeurMin={860}
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
  const { data: personnels } = useQuery({
    queryKey: ['personnels', 'bus', 'chauffeurs'],
    queryFn: () => fetchPersonnels({ fonction_label: 'Chauffeur', per_page: 500 }),
  })
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<BusVehiculePayload>({
    defaultValues: vehicule
      ? {
        immatriculation: vehicule.immatriculation,
        marque: vehicule.marque ?? '',
        couleur: vehicule.couleur ?? '',
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
      school_id: values.school_id ? Number(values.school_id) : undefined,
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
        {!vehicule && (schools?.length ?? 0) > 1 && (
          <Select
            label={`${t('classes.ecole')} *`}
            error={errors.school_id?.message}
            {...register('school_id', { required: "L'école est requise." })}
          >
            <option value="">—</option>
            {schools?.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </Select>
        )}
        <Input
          label={t('bus.immatriculation')}
          error={errors.immatriculation?.message}
          {...register('immatriculation', { required: t('bus.field_required') as string })}
        />
        <div className="grid grid-cols-2 gap-3">
          <Input label={t('bus.marque')} {...register('marque')} />
          <Input label={t('bus.couleur')} placeholder="Ex. Jaune" {...register('couleur')} />
        </div>
        <Input label={t('bus.capacite')} type="number" min={1} {...register('capacite')} />

        <Select label={t('bus.chauffeur')} {...register('chauffeur_id')}>
          <option value="">—</option>
          {personnels?.map((p) => (
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
