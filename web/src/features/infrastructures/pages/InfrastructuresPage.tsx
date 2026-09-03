import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Building2, Pencil, Plus, Sofa, Trash2 } from 'lucide-react'
import {
  creerEquipement,
  creerInfrastructure,
  fetchEquipements,
  fetchInfrastructures,
  modifierEquipement,
  modifierInfrastructure,
  supprimerEquipement,
  supprimerInfrastructure,
  type EquipementMobilier,
  type EquipementMobilierPayload,
  type EtatInfrastructure,
  type Infrastructure,
  type InfrastructurePayload,
  type MateriauInfrastructure,
  type TypeInfrastructure,
} from '@/features/infrastructures/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TYPES: TypeInfrastructure[] = [
  'salle_classe', 'bloc_administratif', 'wc', 'cloture', 'point_eau', 'electricite', 'aire_jeu', 'logement_maitre', 'autre',
]
const MATERIAUX: MateriauInfrastructure[] = ['dur', 'semi_dur', 'provisoire']
const ETATS: EtatInfrastructure[] = ['bon', 'assez_bon', 'mauvais']

const TONE_ETAT: Record<EtatInfrastructure, 'green' | 'gold' | 'red'> = {
  bon: 'green',
  assez_bon: 'gold',
  mauvais: 'red',
}

/** Seuls ces types portent une paire matériau/état — les autres (WC, clôture…) n'ont qu'une quantité brute. */
const TYPES_AVEC_MATERIAU_ETAT: TypeInfrastructure[] = ['salle_classe', 'bloc_administratif']

export function InfrastructuresPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const [infraEnEdition, setInfraEnEdition] = useState<Infrastructure | null>(null)
  const [showInfraForm, setShowInfraForm] = useState(false)
  const [equipementEnEdition, setEquipementEnEdition] = useState<EquipementMobilier | null>(null)
  const [showEquipementForm, setShowEquipementForm] = useState(false)

  const { data: infrastructures, isLoading: chargeInfra } = useQuery({
    queryKey: ['infrastructures'],
    queryFn: fetchInfrastructures,
  })
  const { data: equipements, isLoading: chargeEquipements } = useQuery({
    queryKey: ['infrastructures', 'equipements'],
    queryFn: fetchEquipements,
  })

  const invalidateInfra = () => queryClient.invalidateQueries({ queryKey: ['infrastructures'] })
  const invalidateEquipements = () => queryClient.invalidateQueries({ queryKey: ['infrastructures', 'equipements'] })

  const supprimerUneInfra = async (infra: Infrastructure) => {
    const confirme = await confirmerSuppression(t('infrastructures.confirm_delete', { nom: t(`infrastructures.type_${infra.type}`) }))
    if (!confirme) return
    try {
      await supprimerInfrastructure(infra.id)
      succes(t('infrastructures.deleted'))
      invalidateInfra()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const supprimerUnEquipement = async (equipement: EquipementMobilier) => {
    const confirme = await confirmerSuppression(t('infrastructures.confirm_delete', { nom: equipement.nature }))
    if (!confirme) return
    try {
      await supprimerEquipement(equipement.id)
      succes(t('infrastructures.deleted'))
      invalidateEquipements()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const colonnesInfra: Colonne<Infrastructure>[] = [
    {
      cle: 'type',
      entete: t('infrastructures.type_col'),
      valeur: (i) => t(`infrastructures.type_${i.type}`),
      cellule: (i) => (
        <div>
          <span className="font-semibold text-navy-900">{t(`infrastructures.type_${i.type}`)}</span>
          {i.libelle && <span className="ml-2 text-xs text-navy-400">{i.libelle}</span>}
        </div>
      ),
    },
    {
      cle: 'materiau',
      entete: t('infrastructures.materiau_col'),
      valeur: (i) => i.materiau,
      cellule: (i) => (i.materiau ? t(`infrastructures.materiau_${i.materiau}`) : '—'),
    },
    {
      cle: 'etat',
      entete: t('infrastructures.etat_col'),
      valeur: (i) => i.etat,
      cellule: (i) => (i.etat ? <Badge tone={TONE_ETAT[i.etat]}>{t(`infrastructures.etat_${i.etat}`)}</Badge> : '—'),
    },
    {
      cle: 'quantite',
      entete: t('infrastructures.quantite_col'),
      valeur: (i) => i.quantite,
      cellule: (i) => <span className="tabular-nums font-semibold">{i.quantite}</span>,
    },
    {
      cle: 'besoin',
      entete: t('infrastructures.besoin_col'),
      valeur: (i) => i.besoin_quantite,
      cellule: (i) => (i.besoin_quantite ? <span className="tabular-nums text-gold-600">{i.besoin_quantite}</span> : '—'),
      masquerMobile: true,
    },
    ...(can('infrastructures.manage')
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (i: Infrastructure) => (
              <div className="flex items-center gap-1">
                <button
                  title={t('common.edit')}
                  onClick={() => {
                    setInfraEnEdition(i)
                    setShowInfraForm(true)
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                >
                  <Pencil className="h-4 w-4" />
                </button>
                <button
                  title={t('common.delete')}
                  onClick={() => supprimerUneInfra(i)}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ),
          } satisfies Colonne<Infrastructure>,
        ]
      : []),
  ]

  const colonnesEquipements: Colonne<EquipementMobilier>[] = [
    {
      cle: 'nature',
      entete: t('infrastructures.nature_col'),
      valeur: (e) => e.nature,
      cellule: (e) => <span className="font-semibold text-navy-900">{e.nature}</span>,
    },
    {
      cle: 'quantite',
      entete: t('infrastructures.quantite_col'),
      valeur: (e) => e.quantite,
      cellule: (e) => <span className="tabular-nums font-semibold">{e.quantite}</span>,
    },
    {
      cle: 'besoin',
      entete: t('infrastructures.besoin_col'),
      valeur: (e) => e.besoin_quantite,
      cellule: (e) => (e.besoin_quantite ? <span className="tabular-nums text-gold-600">{e.besoin_quantite}</span> : '—'),
    },
    ...(can('infrastructures.manage')
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (e: EquipementMobilier) => (
              <div className="flex items-center gap-1">
                <button
                  title={t('common.edit')}
                  onClick={() => {
                    setEquipementEnEdition(e)
                    setShowEquipementForm(true)
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                >
                  <Pencil className="h-4 w-4" />
                </button>
                <button
                  title={t('common.delete')}
                  onClick={() => supprimerUnEquipement(e)}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ),
          } satisfies Colonne<EquipementMobilier>,
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('infrastructures.title')}
        sousTitre={t('infrastructures.subtitle')}
        icon={Building2}
      />

      <Card>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
            <Building2 className="h-4 w-4" />
            {t('infrastructures.section_infra')}
          </h2>
          {can('infrastructures.manage') && (
            <div className="flex items-center gap-2">
              <ImportExportBar
                titreImport={t('infrastructures.section_infra')}
                importUrl="/infrastructures/import"
                exportUrl="/infrastructures/export"
                modeleUrl="/infrastructures/modele"
                colonnes={['Type', 'Libellé', 'Matériau', 'État', 'Quantité', 'Besoin (quantité)', 'Observations']}
                nomFichier="infrastructures"
                onImported={invalidateInfra}
              />
              <Button
                onClick={() => {
                  setInfraEnEdition(null)
                  setShowInfraForm(true)
                }}
              >
                <Plus className="h-4 w-4" />
                {t('infrastructures.add')}
              </Button>
            </div>
          )}
        </div>
        {chargeInfra ? (
          <Spinner />
        ) : (
          <DataTable
            colonnes={colonnesInfra}
            lignes={infrastructures ?? []}
            cleLigne={(i) => i.id}
            messageVide={t('infrastructures.empty_infra')}
            largeurMin={640}
          />
        )}
      </Card>

      <Card>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
            <Sofa className="h-4 w-4" />
            {t('infrastructures.section_equipements')}
          </h2>
          {can('infrastructures.manage') && (
            <div className="flex items-center gap-2">
            <ImportExportBar
              titreImport={t('infrastructures.section_equipements')}
              importUrl="/infrastructures/equipements/import"
              exportUrl="/infrastructures/equipements/export"
              modeleUrl="/infrastructures/equipements/modele"
              colonnes={['Nature', 'Quantité', 'Besoin (quantité)']}
              nomFichier="equipements"
              onImported={invalidateEquipements}
            />
            <Button
              onClick={() => {
                setEquipementEnEdition(null)
                setShowEquipementForm(true)
              }}
            >
              <Plus className="h-4 w-4" />
              {t('infrastructures.add')}
            </Button>
            </div>
          )}
        </div>
        {chargeEquipements ? (
          <Spinner />
        ) : (
          <DataTable
            colonnes={colonnesEquipements}
            lignes={equipements ?? []}
            cleLigne={(e) => e.id}
            messageVide={t('infrastructures.empty_equipements')}
            largeurMin={480}
          />
        )}
      </Card>

      {showInfraForm && (
        <InfrastructureFormModal
          infrastructure={infraEnEdition}
          onClose={() => setShowInfraForm(false)}
          onSaved={() => {
            setShowInfraForm(false)
            setInfraEnEdition(null)
            invalidateInfra()
          }}
        />
      )}

      {showEquipementForm && (
        <EquipementFormModal
          equipement={equipementEnEdition}
          onClose={() => setShowEquipementForm(false)}
          onSaved={() => {
            setShowEquipementForm(false)
            setEquipementEnEdition(null)
            invalidateEquipements()
          }}
        />
      )}
    </div>
  )
}

function InfrastructureFormModal({
  infrastructure,
  onClose,
  onSaved,
}: {
  infrastructure: Infrastructure | null
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    formState: { isSubmitting, errors },
  } = useForm<InfrastructurePayload>({
    defaultValues: infrastructure
      ? {
          type: infrastructure.type,
          libelle: infrastructure.libelle ?? '',
          materiau: infrastructure.materiau ?? undefined,
          etat: infrastructure.etat ?? undefined,
          quantite: infrastructure.quantite,
          besoin_quantite: infrastructure.besoin_quantite ?? undefined,
          observations: infrastructure.observations ?? '',
        }
      : { type: 'salle_classe', quantite: 1 },
  })

  const typeChoisi = watch('type')
  const avecMaterielEtat = TYPES_AVEC_MATERIAU_ETAT.includes(typeChoisi)

  const onSubmit = async (values: InfrastructurePayload) => {
    setServerError(null)
    const payload: InfrastructurePayload = {
      ...values,
      libelle: values.libelle || null,
      materiau: avecMaterielEtat ? values.materiau || null : null,
      etat: avecMaterielEtat ? values.etat || null : null,
      quantite: Number(values.quantite),
      besoin_quantite: values.besoin_quantite ? Number(values.besoin_quantite) : null,
      observations: values.observations || null,
    }

    try {
      if (infrastructure) {
        await modifierInfrastructure(infrastructure.id, payload)
        succes(t('infrastructures.updated'))
      } else {
        await creerInfrastructure(payload)
        succes(t('infrastructures.created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={infrastructure ? t('infrastructures.edit_title') : t('infrastructures.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select label={t('infrastructures.type_col')} {...register('type', { required: true })}>
          {TYPES.map((valeur) => (
            <option key={valeur} value={valeur}>
              {t(`infrastructures.type_${valeur}`)}
            </option>
          ))}
        </Select>

        <Input label={t('infrastructures.libelle_label')} placeholder={t('infrastructures.libelle_placeholder')} {...register('libelle')} />

        {avecMaterielEtat && (
          <div className="grid grid-cols-2 gap-3">
            <Select label={t('infrastructures.materiau_col')} {...register('materiau')}>
              <option value="">—</option>
              {MATERIAUX.map((valeur) => (
                <option key={valeur} value={valeur}>
                  {t(`infrastructures.materiau_${valeur}`)}
                </option>
              ))}
            </Select>
            <Select label={t('infrastructures.etat_col')} {...register('etat')}>
              <option value="">—</option>
              {ETATS.map((valeur) => (
                <option key={valeur} value={valeur}>
                  {t(`infrastructures.etat_${valeur}`)}
                </option>
              ))}
            </Select>
          </div>
        )}

        <div className="grid grid-cols-2 gap-3">
          <Input
            label={t('infrastructures.quantite_col')}
            type="number"
            min={0}
            error={errors.quantite?.message}
            {...register('quantite', { required: true, min: { value: 0, message: t('infrastructures.quantite_min') as string } })}
          />
          <Input label={t('infrastructures.besoin_col')} type="number" min={0} {...register('besoin_quantite')} />
        </div>

        <Input label={t('infrastructures.observations_label')} {...register('observations')} />

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

function EquipementFormModal({
  equipement,
  onClose,
  onSaved,
}: {
  equipement: EquipementMobilier | null
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<EquipementMobilierPayload>({
    defaultValues: equipement
      ? { nature: equipement.nature, quantite: equipement.quantite, besoin_quantite: equipement.besoin_quantite ?? undefined }
      : { quantite: 0 },
  })

  const onSubmit = async (values: EquipementMobilierPayload) => {
    setServerError(null)
    const payload: EquipementMobilierPayload = {
      ...values,
      quantite: Number(values.quantite),
      besoin_quantite: values.besoin_quantite ? Number(values.besoin_quantite) : null,
    }

    try {
      if (equipement) {
        await modifierEquipement(equipement.id, payload)
        succes(t('infrastructures.updated'))
      } else {
        await creerEquipement(payload)
        succes(t('infrastructures.created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={equipement ? t('infrastructures.edit_title') : t('infrastructures.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('infrastructures.nature_col')}
          placeholder={t('infrastructures.nature_placeholder')}
          error={errors.nature?.message}
          {...register('nature', { required: t('bus.field_required') as string })}
        />
        <div className="grid grid-cols-2 gap-3">
          <Input
            label={t('infrastructures.quantite_col')}
            type="number"
            min={0}
            error={errors.quantite?.message}
            {...register('quantite', { required: true, min: { value: 0, message: t('infrastructures.quantite_min') as string } })}
          />
          <Input label={t('infrastructures.besoin_col')} type="number" min={0} {...register('besoin_quantite')} />
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
