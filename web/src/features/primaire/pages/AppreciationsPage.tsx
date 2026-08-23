import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, SmilePlus, Trash2 } from 'lucide-react'
import {
  fetchAppreciations,
  creerAppreciation,
  modifierAppreciation,
  supprimerAppreciation,
  type Appreciation,
  type AppreciationPayload,
} from '@/features/primaire/api'
import { fetchSchools } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmer, confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Référentiel d'appréciations de la maternelle : les visages que l'enseignante
 * coche, et les couleurs dont le bulletin remplit ses cases.
 *
 * L'ordre décide de la position des colonnes sur le document — le modifier
 * change la lecture du bulletin, pas seulement cet écran.
 */
export function AppreciationsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [formOuvert, setFormOuvert] = useState(false)
  const [enEdition, setEnEdition] = useState<Appreciation | null>(null)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  const { data: appreciations, isLoading } = useQuery({
    queryKey: ['appreciations'],
    queryFn: fetchAppreciations,
  })

  const invalider = () => {
    queryClient.invalidateQueries({ queryKey: ['appreciations'] })
    queryClient.invalidateQueries({ queryKey: ['grille-primaire'] })
  }

  const basculerSelection = (id: number) => {
    setSelectedIds((actuels) => {
      const suivants = new Set(actuels)
      if (suivants.has(id)) suivants.delete(id)
      else suivants.add(id)
      return suivants
    })
  }

  const basculerToutes = () => {
    const lignes = appreciations ?? []
    setSelectedIds((actuels) => actuels.size === lignes.length ? new Set() : new Set(lignes.map((a) => a.id)))
  }

  const supprimerSelection = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return

    const ok = await confirmer({
      titre: t('appreciations.supprimer_selection_titre', { count: ids.length }),
      message: t('alerts.irreversible'),
      action: t('common.delete'),
    })
    if (!ok) return

    try {
      await Promise.all(ids.map((id) => supprimerAppreciation(id)))
      setSelectedIds(new Set())
      invalider()
      succes(t('appreciations.supprimes', { count: ids.length }))
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const colonnes: Colonne<Appreciation>[] = [
    ...(can('pedagogie.manage')
      ? [{
        cle: 'selection',
        entete: (
          <input
            type="checkbox"
            checked={(appreciations?.length ?? 0) > 0 && selectedIds.size === appreciations?.length}
            onChange={basculerToutes}
            className="h-4 w-4 rounded border-navy-300"
            aria-label={t('common.selectAll')}
          />
        ),
        valeur: () => '',
        cellule: (a: Appreciation) => (
          <input
            type="checkbox"
            checked={selectedIds.has(a.id)}
            onChange={() => basculerSelection(a.id)}
            className="h-4 w-4 rounded border-navy-300"
            aria-label={a.label_fr}
          />
        ),
      } satisfies Colonne<Appreciation>]
      : []),
    {
      cle: 'ordre',
      entete: t('appreciations.ordre'),
      valeur: (a) => a.ordre,
      cellule: (a) => <span className="tabular-nums text-navy-500">{a.ordre}</span>,
    },
    {
      cle: 'apercu',
      entete: t('appreciations.apercu'),
      cellule: (a) => (
        <span
          className="flex h-8 w-12 items-center justify-center rounded-lg text-lg text-white shadow-soft"
          style={{ backgroundColor: a.couleur }}
          title={a.couleur}
        >
          {a.emoji ?? ''}
        </span>
      ),
    },
    {
      cle: 'label',
      entete: t('appreciations.libelle'),
      valeur: (a) => a.label_fr,
      cellule: (a) => (
        <span className="flex flex-col">
          <span className="font-semibold text-navy-900">{a.label_fr}</span>
          {a.label_en && <span className="text-xs text-navy-400">{a.label_en}</span>}
        </span>
      ),
    },
    {
      cle: 'couleur',
      entete: t('appreciations.couleur'),
      valeur: (a) => a.couleur,
      cellule: (a) => <span className="font-mono text-xs text-navy-500">{a.couleur}</span>,
      masquerMobile: true,
    },
    {
      cle: 'statut',
      entete: t('eleves.statut'),
      valeur: (a) => a.statut ?? 'actif',
      cellule: (a) => (
        <Badge tone={a.statut === 'inactif' ? 'neutral' : 'green'}>
          {a.statut === 'inactif' ? t('appreciations.inactif') : t('appreciations.actif')}
        </Badge>
      ),
    },
    ...(can('pedagogie.manage')
      ? [
        {
          cle: 'actions',
          entete: t('common.actions'),
          cellule: (a: Appreciation) => (
            <div className="flex items-center gap-1">
              <button
                title={t('common.edit')}
                onClick={() => {
                  setEnEdition(a)
                  setFormOuvert(true)
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
              >
                <Pencil className="h-4 w-4" />
              </button>
              <button
                title={t('common.delete')}
                onClick={async () => {
                  if (!(await confirmerSuppression(a.label_fr))) return
                  try {
                    await supprimerAppreciation(a.id)
                    invalider()
                    succes(t('appreciations.supprime'))
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
        } satisfies Colonne<Appreciation>,
      ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('appreciations.title')}
        sousTitre={t('appreciations.subtitle')}
        icon={SmilePlus}
        actions={
          can('pedagogie.manage') && (
            <Button
              onClick={() => {
                setEnEdition(null)
                setFormOuvert(true)
              }}
            >
              <Plus className="h-4 w-4" />
              {t('appreciations.ajouter')}
            </Button>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={appreciations ?? []}
          cleLigne={(a) => a.id}
          placeholderRecherche={t('appreciations.recherche')}
          messageVide={t('appreciations.aucun_niveau')}
          largeurMin={720}
          outils={can('pedagogie.manage') && selectedIds.size > 0 ? (
            <Button variant="danger" onClick={supprimerSelection}>
              <Trash2 className="h-4 w-4" />
              {t('appreciations.supprimer_selection', { count: selectedIds.size })}
            </Button>
          ) : undefined}
        />
      )}

      {formOuvert && (
        <AppreciationFormModal
          appreciation={enEdition}
          onClose={() => {
            setFormOuvert(false)
            setEnEdition(null)
          }}
          onSaved={() => {
            setFormOuvert(false)
            setEnEdition(null)
            invalider()
          }}
        />
      )}
    </div>
  )
}

function AppreciationFormModal({
  appreciation,
  onClose,
  onSaved,
}: {
  appreciation: Appreciation | null
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })

  const { register, handleSubmit, watch, formState: { isSubmitting, errors } } = useForm<AppreciationPayload>({
    defaultValues: appreciation
      ? {
        label_fr: appreciation.label_fr,
        label_en: appreciation.label_en ?? '',
        emoji: appreciation.emoji ?? '',
        couleur: appreciation.couleur,
        ordre: appreciation.ordre,
        statut: appreciation.statut ?? 'actif',
      }
      : { couleur: '#16a34a', ordre: 1, statut: 'actif' },
  })

  const couleur = watch('couleur')
  const emoji = watch('emoji')

  const onSubmit = async (values: AppreciationPayload) => {
    setServerError(null)
    try {
      const payload: AppreciationPayload = {
        ...values,
        ordre: Number(values.ordre),
        emoji: values.emoji?.trim() || null,
        school_id: values.school_id ? Number(values.school_id) : null,
      }

      if (appreciation) {
        await modifierAppreciation(appreciation.id, payload)
        succes(t('appreciations.modifie'))
      } else {
        await creerAppreciation(payload)
        succes(t('appreciations.cree'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={appreciation ? t('appreciations.modifier') : t('appreciations.ajouter')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        {!appreciation && (schools?.length ?? 0) > 1 && (
          <Select
            label={`${t('classes.ecole')} *`}
            error={errors.school_id?.message}
            {...register('school_id', { required: "L'école est requise." })}
          >
            <option value="">—</option>
            {schools?.map((school) => (
              <option key={school.id} value={school.id}>
                {school.name}
              </option>
            ))}
          </Select>
        )}

        <Input label={t('appreciations.libelle')} {...register('label_fr', { required: true })} />
        <Input label={t('appreciations.libelle_en')} {...register('label_en')} />

        <div className="grid grid-cols-2 gap-3">
          <Input
            label={t('appreciations.emoji')}
            placeholder="🙂"
            maxLength={4}
            {...register('emoji')}
          />
          <Input
            type="number"
            min={1}
            max={20}
            label={t('appreciations.ordre')}
            {...register('ordre', { required: true })}
          />
        </div>

        <div className="flex items-end gap-3">
          <div className="flex-1">
            <Input
              label={t('appreciations.couleur')}
              placeholder="#16a34a"
              {...register('couleur', {
                required: true,
                pattern: { value: /^#[0-9a-fA-F]{6}$/, message: t('appreciations.couleur_format') },
              })}
              error={errors.couleur?.message}
            />
          </div>
          {/* Aperçu de la case telle qu'elle sortira sur le bulletin. */}
          <span
            className="mb-1 flex h-10 w-16 flex-none items-center justify-center rounded-lg text-xl text-white shadow-soft"
            style={{ backgroundColor: /^#[0-9a-fA-F]{6}$/.test(couleur ?? '') ? couleur : '#cbd5e1' }}
          >
            {emoji}
          </span>
        </div>

        <Select label={t('eleves.statut')} {...register('statut')}>
          <option value="actif">{t('appreciations.actif')}</option>
          <option value="inactif">{t('appreciations.inactif')}</option>
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
