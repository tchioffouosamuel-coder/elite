import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { Clock, MapPin, Pencil, Plus, Trash2, Upload } from 'lucide-react'
import {
  ajouterArret,
  fetchTrajets,
  modifierArret,
  supprimerArret,
  type BusArret,
  type BusArretPayload,
  type BusTrajet,
} from '@/features/bus/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { ImportModal } from '@/shared/ui/ImportModal'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface LigneArret extends BusArret {
  trajetId: number
  trajetNom: string
}

type ArretFormValues = {
  trajet_id: number
  nom: string
  lieu_dit?: string | null
  lieu_ramassage?: string | null
  lieu_depot?: string | null
  ordre?: number | string | null
  heure_passage?: string | null
  tarif_aller_simple?: number | string | null
  tarif_retour_simple?: number | string | null
  tarif_aller_retour?: number | string | null
}

export function BusArretsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [showImport, setShowImport] = useState(false)
  const [arretEnEdition, setArretEnEdition] = useState<LigneArret | null>(null)

  const { data: trajets, isLoading } = useQuery({ queryKey: ['bus-trajets'], queryFn: fetchTrajets })

  const lignes: LigneArret[] = (trajets ?? []).flatMap((t) =>
    t.arrets.map((a) => ({ ...a, trajetId: t.id, trajetNom: t.nom })),
  )

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['bus-trajets'] })

  const colonnes: Colonne<LigneArret>[] = [
    {
      cle: 'nom',
      entete: t('bus.nom'),
      valeur: (l) => l.nom,
      cellule: (l) => (
        <span className="inline-flex items-center gap-1.5 font-semibold text-navy-900">
          <MapPin className="h-3.5 w-3.5 text-navy-300" />
          {l.nom}
        </span>
      ),
    },
    {
      cle: 'trajet',
      entete: t('bus.trajet_select'),
      valeur: (l) => l.trajetNom,
      cellule: (l) => (
        <Link to={`/bus/trajets/${l.trajetId}`} className="text-navy-700 hover:text-gold-600 hover:underline">
          {l.trajetNom}
        </Link>
      ),
    },
    {
      cle: 'lieu_dit',
      entete: t('bus.lieu_dit'),
      valeur: (l) => l.lieu_dit,
      cellule: (l) => <span className="text-navy-600">{l.lieu_dit || '—'}</span>,
    },
    {
      cle: 'lieu_ramassage',
      entete: t('bus.lieu_ramassage'),
      valeur: (l) => l.lieu_ramassage,
      cellule: (l) => <span className="text-navy-600">{l.lieu_ramassage || '—'}</span>,
    },
    {
      cle: 'lieu_depot',
      entete: t('bus.lieu_depot'),
      valeur: (l) => l.lieu_depot,
      cellule: (l) => <span className="text-navy-600">{l.lieu_depot || '—'}</span>,
    },
    {
      cle: 'tarifs',
      entete: t('bus.tarifs'),
      valeur: (l) => `${l.tarif_aller_simple ?? ''} ${l.tarif_retour_simple ?? ''} ${l.tarif_aller_retour ?? ''}`,
      cellule: (l) => (
        <span className="text-xs tabular-nums text-navy-600">
          {l.tarif_aller_simple ?? '—'} / {l.tarif_retour_simple ?? '—'} / {l.tarif_aller_retour ?? '—'}
        </span>
      ),
    },
    {
      cle: 'ordre',
      entete: t('bus.ordre'),
      valeur: (l) => l.ordre,
      cellule: (l) => <span className="tabular-nums">{l.ordre}</span>,
    },
    {
      cle: 'heure_passage',
      entete: t('bus.heure_passage'),
      valeur: (l) => l.heure_passage,
      cellule: (l) =>
        l.heure_passage ? (
          <span className="inline-flex items-center gap-1 text-navy-600">
            <Clock className="h-3.5 w-3.5 text-navy-300" />
            {l.heure_passage}
          </span>
        ) : (
          '—'
        ),
    },
    ...(can('bus.manage')
      ? [
        {
          cle: 'actions',
          entete: t('common.actions'),
          cellule: (l: LigneArret) => (
            <div className="flex items-center gap-1">
              <button
                title={t('common.edit')}
                onClick={() => {
                  setArretEnEdition(l)
                  setShowForm(true)
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
              >
                <Pencil className="h-4 w-4" />
              </button>
              <button
                title={t('common.delete')}
                onClick={async () => {
                  if (!(await confirmerSuppression(l.nom))) return
                  try {
                    await supprimerArret(l.trajetId, l.id)
                    invalidate()
                    succes(t('bus.arret_deleted'))
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
        } satisfies Colonne<LigneArret>,
      ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('bus.arrets_title')}
        sousTitre={t('bus.arrets_subtitle')}
        icon={MapPin}
        actions={
          can('bus.manage') && (
            <>
              <Button variant="secondary" onClick={() => setShowImport(true)}>
                <Upload className="h-4 w-4" />
                {t('import.title')}
              </Button>
              <Button
                onClick={() => {
                  setArretEnEdition(null)
                  setShowForm(true)
                }}
                disabled={!trajets || trajets.length === 0}
              >
                <Plus className="h-4 w-4" />
                {t('bus.arret_add')}
              </Button>
            </>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={lignes}
          cleLigne={(l) => l.id}
          placeholderRecherche={t('bus.search_arret')}
          messageVide={t('bus.empty_arrets_page')}
          largeurMin={640}
        />
      )}

      {showForm && (
        <ArretFormModal
          trajets={trajets ?? []}
          arret={arretEnEdition}
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false)
            setArretEnEdition(null)
            invalidate()
          }}
        />
      )}

      {showImport && (
        <ImportModal
          title={t('import.title')}
          url="/bus/arrets/import"
          columns={['trajet', 'nom', 'lieu_dit', 'lieu_ramassage', 'lieu_depot', 'ordre', 'heure_passage', 'tarif_aller_simple', 'tarif_retour_simple', 'tarif_aller_retour']}
          onClose={() => setShowImport(false)}
          onImported={invalidate}
        />
      )}
    </div>
  )
}

function ArretFormModal({
  trajets,
  arret,
  onClose,
  onSaved,
}: {
  trajets: BusTrajet[]
  arret: LigneArret | null
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<ArretFormValues>({
    defaultValues: arret
      ? {
        trajet_id: arret.trajetId,
        nom: arret.nom,
        lieu_dit: arret.lieu_dit ?? '',
        lieu_ramassage: arret.lieu_ramassage ?? '',
        lieu_depot: arret.lieu_depot ?? '',
        ordre: arret.ordre,
        heure_passage: arret.heure_passage ?? '',
        tarif_aller_simple: arret.tarif_aller_simple ?? '',
        tarif_retour_simple: arret.tarif_retour_simple ?? '',
        tarif_aller_retour: arret.tarif_aller_retour ?? '',
      }
      : { trajet_id: trajets[0]?.id, ordre: 1 },
  })

  const onSubmit = async (values: ArretFormValues) => {
    setServerError(null)
    const trajetId = Number(values.trajet_id)
    const payload: BusArretPayload = {
      nom: values.nom,
      lieu_dit: values.lieu_dit || null,
      lieu_ramassage: values.lieu_ramassage || null,
      lieu_depot: values.lieu_depot || null,
      ordre: values.ordre ? Number(values.ordre) : null,
      heure_passage: values.heure_passage || null,
      tarif_aller_simple: values.tarif_aller_simple === '' ? null : Number(values.tarif_aller_simple),
      tarif_retour_simple: values.tarif_retour_simple === '' ? null : Number(values.tarif_retour_simple),
      tarif_aller_retour: values.tarif_aller_retour === '' ? null : Number(values.tarif_aller_retour),
    }

    try {
      if (arret) {
        await modifierArret(arret.trajetId, arret.id, payload)
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
        {arret ? (
          <div className="flex flex-col gap-1.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('bus.trajet_select')}</span>
            <span className="text-sm font-semibold text-navy-800">{arret.trajetNom}</span>
          </div>
        ) : (
          <Select label={t('bus.trajet_select')} {...register('trajet_id', { required: true })}>
            {trajets.map((tr) => (
              <option key={tr.id} value={tr.id}>
                {tr.nom}
              </option>
            ))}
          </Select>
        )}

        <Input
          label={t('bus.nom')}
          error={errors.nom?.message}
          {...register('nom', { required: t('bus.field_required') as string })}
        />
        <Input label={t('bus.lieu_dit')} {...register('lieu_dit')} />
        <Input label={t('bus.lieu_ramassage')} {...register('lieu_ramassage')} />
        <Input label={t('bus.lieu_depot')} {...register('lieu_depot')} />
        <Input label={t('bus.ordre')} type="number" min={1} {...register('ordre')} />
        <Input label={t('bus.heure_passage')} type="time" {...register('heure_passage')} />

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <Input label={t('bus.tarif_aller_simple')} type="number" min={0} {...register('tarif_aller_simple')} />
          <Input label={t('bus.tarif_retour_simple')} type="number" min={0} {...register('tarif_retour_simple')} />
          <Input label={t('bus.tarif_aller_retour')} type="number" min={0} {...register('tarif_aller_retour')} />
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
