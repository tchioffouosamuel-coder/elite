import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Check, FileDown, Plus, Trash2, X } from 'lucide-react'
import { fetchSanctions, createSanction, updateSanction, deleteSanction, ouvrirPvConseil } from '@/features/discipline/api'
import type { Sanction, SanctionPayload, StatutSanction, TypeSanction } from '@/features/discipline/api'
import { fetchClasses } from '@/features/classes/api'
import { fetchEleves } from '@/features/eleves/api'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Badge } from '@/shared/ui/Badge'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmerSuppression, confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TYPE_TONE: Record<TypeSanction, 'gold' | 'red' | 'neutral' | 'blue'> = {
  avertissement: 'blue',
  blame: 'gold',
  corvee: 'gold',
  exclusion_temporaire: 'red',
  exclusion_definitive: 'red',
  autre: 'neutral',
}

const STATUT_TONE: Record<StatutSanction, 'gold' | 'green' | 'neutral'> = {
  en_attente: 'gold',
  confirmee: 'green',
  annulee: 'neutral',
}

// Une exclusion temporaire n'a de sens qu'avec une durée à borner ; les autres
// types n'ont rien à saisir dans ce champ.
const TYPES_AVEC_DUREE: TypeSanction[] = ['exclusion_temporaire']

export function SanctionsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [classeFiltre, setClasseFiltre] = useState<number | ''>('')
  const [showForm, setShowForm] = useState(false)

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]
  const { data: sanctions, isLoading } = useQuery({
    queryKey: ['sanctions', classeFiltre],
    queryFn: () => fetchSanctions(classeFiltre ? { classe_id: Number(classeFiltre) } : {}),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['sanctions'] })

  const genererPv = async () => {
    if (!trimestreActif) {
      erreur(t('discipline.no_trimestre_actif'))
      return
    }
    try {
      await ouvrirPvConseil(trimestreActif.id, classeFiltre ? Number(classeFiltre) : undefined)
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  // Le conseil de discipline tranche : confirmer entérine la sanction (elle
  // compte désormais dans le dossier de l'élève, et peut valoir exclusion
  // active), annuler la classe sans suite sans effacer qu'elle a existé.
  const trancher = async (sanction: Sanction, statut: StatutSanction) => {
    if (statut === 'annulee') {
      const ok = await confirmer({
        titre: t('discipline.cancel_sanction_title'),
        message: t('discipline.cancel_sanction_message', { nom: sanction.eleve.nom_complet }),
        action: t('discipline.cancel_sanction_action'),
      })
      if (!ok) return
    }

    try {
      await updateSanction(sanction.id, { statut })
      invalidate()
      succes(statut === 'confirmee' ? t('discipline.sanction_confirmee') : t('discipline.sanction_annulee'))
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const colonnes: Colonne<Sanction>[] = [
    {
      cle: 'eleve',
      entete: t('eleves.nom_complet'),
      valeur: (s) => s.eleve.nom_complet,
      cellule: (s) => <span className="font-semibold text-navy-900">{s.eleve.nom_complet}</span>,
    },
    { cle: 'classe', entete: t('classes.title'), valeur: (s) => s.classe, cellule: (s) => s.classe ?? '—' },
    {
      cle: 'type',
      entete: t('discipline.type'),
      valeur: (s) => s.type,
      cellule: (s) => (
        <>
          <Badge tone={TYPE_TONE[s.type]}>{t(`discipline.type_${s.type}`)}</Badge>
          {s.duree_jours && <span className="ml-1 text-xs text-navy-400">({s.duree_jours}j)</span>}
        </>
      ),
    },
    {
      cle: 'motif',
      entete: t('discipline.motif'),
      valeur: (s) => s.motif,
      cellule: (s) => <span className="block max-w-xs truncate">{s.motif}</span>,
      masquerMobile: true,
    },
    {
      cle: 'date',
      entete: t('discipline.date'),
      valeur: (s) => s.date_sanction,
      cellule: (s) => new Date(s.date_sanction).toLocaleDateString('fr-FR'),
    },
    {
      cle: 'statut',
      entete: t('discipline.statut'),
      valeur: (s) => s.statut,
      cellule: (s) => <Badge tone={STATUT_TONE[s.statut]}>{t(`discipline.statut_${s.statut}`)}</Badge>,
    },
    ...(can('discipline.manage')
      ? [
        {
          cle: 'actions',
          entete: t('common.actions'),
          cellule: (s: Sanction) => (
            <div className="flex items-center gap-1">
              {s.statut === 'en_attente' && (
                <>
                  <button
                    title={t('discipline.confirmer')}
                    onClick={() => trancher(s, 'confirmee')}
                    className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-green-50 hover:text-green-600"
                  >
                    <Check className="h-4 w-4" />
                  </button>
                  <button
                    title={t('discipline.annuler_sanction')}
                    onClick={() => trancher(s, 'annulee')}
                    className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </>
              )}
              <button
                title={t('common.delete')}
                onClick={async () => {
                  if (!(await confirmerSuppression(t('discipline.delete_target', { nom: s.eleve.nom_complet })))) return
                  await deleteSanction(s.id)
                  invalidate()
                  succes(t('alerts.sanction_deleted'))
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ),
        } satisfies Colonne<Sanction>,
      ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('discipline.sanctions')}</h1>
        {can('discipline.manage') && (
          <div className="flex items-center gap-2">
            <Button variant="secondary" onClick={genererPv}>
              <FileDown className="h-4 w-4" />
              {t('discipline.pv_conseil')}
            </Button>
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              {t('discipline.add_sanction')}
            </Button>
          </div>
        )}
      </div>

      <Select value={classeFiltre} onChange={(e) => setClasseFiltre(e.target.value ? Number(e.target.value) : '')} className="max-w-xs">
        <option value="">{t('common.all')}</option>
        {classes?.map((c) => (
          <option key={c.id} value={c.id}>
            {c.nom}
          </option>
        ))}
      </Select>

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={sanctions ?? []}
          cleLigne={(s) => s.id}
          placeholderRecherche={t('discipline.search_placeholder')}
          messageVide={t('discipline.empty_sanctions')}
          largeurMin={860}
        />
      )}

      {showForm && (
        <SanctionFormModal
          onClose={() => setShowForm(false)}
          onCreated={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}
    </div>
  )
}

function SanctionFormModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const { data: eleves } = useQuery({ queryKey: ['eleves', 'all'], queryFn: () => fetchEleves({ per_page: 200 }) })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]

  const {
    register,
    handleSubmit,
    watch,
    formState: { isSubmitting, errors },
  } = useForm<SanctionPayload>({
    defaultValues: { type: 'avertissement', date_sanction: new Date().toISOString().slice(0, 10), impacte_bulletin: false },
  })

  const typeChoisi = watch('type')

  const onSubmit = async (values: SanctionPayload) => {
    setServerError(null)
    try {
      await createSanction({
        ...values,
        eleve_id: Number(values.eleve_id),
        trimestre_id: trimestreActif ? trimestreActif.id : Number(values.trimestre_id),
        duree_jours: values.duree_jours ? Number(values.duree_jours) : null,
        impacte_bulletin: Boolean(values.impacte_bulletin),
      })
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={t('discipline.add_sanction')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select label={t('eleves.title')} error={errors.eleve_id?.message} {...register('eleve_id', { required: true })}>
          <option value="">—</option>
          {eleves?.items.map((e) => (
            <option key={e.id} value={e.id}>
              {e.nom_complet} — {e.classe?.nom}
            </option>
          ))}
        </Select>
        <Select label={t('discipline.type')} {...register('type', { required: true })}>
          <option value="avertissement">{t('discipline.type_avertissement')}</option>
          <option value="blame">{t('discipline.type_blame')}</option>
          <option value="corvee">{t('discipline.type_corvee')}</option>
          <option value="exclusion_temporaire">{t('discipline.type_exclusion_temporaire')}</option>
          <option value="exclusion_definitive">{t('discipline.type_exclusion_definitive')}</option>
          <option value="autre">{t('discipline.type_autre')}</option>
        </Select>
        <div className="grid grid-cols-2 gap-3">
          {TYPES_AVEC_DUREE.includes(typeChoisi) && (
            <Input
              label={t('discipline.duree_jours')}
              type="number"
              min={1}
              error={errors.duree_jours?.message}
              {...register('duree_jours', { required: TYPES_AVEC_DUREE.includes(typeChoisi) })}
            />
          )}
          <Input label={t('discipline.date')} type="date" {...register('date_sanction', { required: true })} />
        </div>
        <Textarea
          label={t('discipline.motif')}
          error={errors.motif?.message}
          {...register('motif', { required: true, minLength: { value: 10, message: t('discipline.motif_min_length') } })}
        />
        <Textarea label={t('discipline.commentaire')} rows={2} {...register('commentaire')} />
        <label className="flex cursor-pointer items-center gap-2 text-sm text-navy-700">
          <input type="checkbox" className="h-4 w-4 rounded border-navy-300" {...register('impacte_bulletin')} />
          {t('discipline.impacte_bulletin')}
        </label>

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
