import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Plus, Trash2, Gavel } from 'lucide-react'
import {
  fetchRevendications,
  creerRevendication,
  traiterRevendication,
  supprimerRevendication,
} from '@/features/revendications/api'
import type { Revendication, RevendicationPayload, StatutRevendication } from '@/features/revendications/api'
import { fetchEleves } from '@/features/eleves/api'
import { fetchClasseMatieres, fetchTrimestres } from '@/features/pedagogie/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Badge } from '@/shared/ui/Badge'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmerSuppression, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const STATUT_TONE: Record<StatutRevendication, 'gold' | 'blue' | 'green' | 'red'> = {
  en_attente: 'gold',
  en_cours: 'blue',
  resolue: 'green',
  rejetee: 'red',
}

/** L'école n'a pas de portail parent/élève : c'est l'administration qui saisit
 *  la réclamation pour le compte du tuteur venu se plaindre en personne. */
export function RevendicationsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [statutFiltre, setStatutFiltre] = useState<StatutRevendication | ''>('')
  const [showForm, setShowForm] = useState(false)
  const [aTraiter, setATraiter] = useState<Revendication | null>(null)

  const { data: revendications, isLoading } = useQuery({
    queryKey: ['revendications', statutFiltre],
    queryFn: () => fetchRevendications(statutFiltre ? { statut: statutFiltre } : {}),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['revendications'] })

  const colonnes: Colonne<Revendication>[] = [
    {
      cle: 'eleve',
      entete: t('eleves.nom_complet'),
      valeur: (r) => r.eleve.nom_complet,
      cellule: (r) => (
        <div>
          <span className="font-semibold text-navy-900">{r.eleve.nom_complet}</span>
          <div className="text-xs text-navy-400">{r.eleve.classe ?? '—'}</div>
        </div>
      ),
    },
    {
      cle: 'type',
      entete: t('revendications.type'),
      valeur: (r) => r.type,
      cellule: (r) => (
        <div>
          <Badge tone="neutral">{t(`revendications.type_${r.type}`)}</Badge>
          {r.matiere && <div className="mt-1 text-xs text-navy-400">{r.matiere}</div>}
        </div>
      ),
    },
    {
      cle: 'objet',
      entete: t('revendications.objet'),
      valeur: (r) => r.objet,
      cellule: (r) => (
        <div>
          <span className="font-medium text-navy-800">{r.objet}</span>
          <p className="mt-0.5 max-w-xs truncate text-xs text-navy-400">{r.motif}</p>
        </div>
      ),
      masquerMobile: true,
    },
    {
      cle: 'date',
      entete: t('revendications.date_reception'),
      valeur: (r) => r.date_reception,
      cellule: (r) => new Date(r.date_reception).toLocaleDateString('fr-FR'),
    },
    {
      cle: 'statut',
      entete: t('revendications.statut'),
      valeur: (r) => r.statut,
      cellule: (r) => <Badge tone={STATUT_TONE[r.statut]}>{t(`revendications.statut_${r.statut}`)}</Badge>,
    },
    ...(can('revendications.manage')
      ? [
        {
          cle: 'actions',
          entete: t('common.actions'),
          cellule: (r: Revendication) => (
            <div className="flex items-center gap-1">
              {(r.statut === 'en_attente' || r.statut === 'en_cours') && (
                <button
                  title={t('revendications.traiter')}
                  onClick={() => setATraiter(r)}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-gold-50 hover:text-gold-600"
                >
                  <Gavel className="h-4 w-4" />
                </button>
              )}
              <button
                title={t('common.delete')}
                onClick={async () => {
                  if (!(await confirmerSuppression(t('revendications.delete_target', { objet: r.objet })))) return
                  await supprimerRevendication(r.id)
                  invalidate()
                  succes(t('revendications.deleted'))
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ),
        } satisfies Colonne<Revendication>,
      ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('revendications.title')}
        sousTitre={t('revendications.subtitle')}
        icon={Gavel}
        actions={
          can('revendications.manage') && (
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              {t('revendications.add')}
            </Button>
          )
        }
      />

      <Select
        value={statutFiltre}
        onChange={(e) => setStatutFiltre(e.target.value as StatutRevendication | '')}
        className="max-w-xs"
      >
        <option value="">{t('common.all')}</option>
        <option value="en_attente">{t('revendications.statut_en_attente')}</option>
        <option value="en_cours">{t('revendications.statut_en_cours')}</option>
        <option value="resolue">{t('revendications.statut_resolue')}</option>
        <option value="rejetee">{t('revendications.statut_rejetee')}</option>
      </Select>

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={revendications ?? []}
          cleLigne={(r) => r.id}
          placeholderRecherche={t('revendications.search_placeholder')}
          messageVide={t('revendications.empty')}
          largeurMin={860}
        />
      )}

      {showForm && (
        <RevendicationFormModal
          onClose={() => setShowForm(false)}
          onCreated={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}

      {aTraiter && (
        <TraiterModal
          revendication={aTraiter}
          onClose={() => setATraiter(null)}
          onTraitee={() => {
            setATraiter(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}

function RevendicationFormModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
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
  } = useForm<RevendicationPayload>({
    defaultValues: { type: 'note', date_reception: new Date().toISOString().slice(0, 10) },
  })

  const typeChoisi = watch('type')
  const eleveChoisiId = watch('eleve_id')
  const eleveChoisi = eleves?.items.find((e) => e.id === Number(eleveChoisiId))

  const { data: matieres } = useQuery({
    queryKey: ['classe-matieres', eleveChoisi?.classe?.id],
    queryFn: () => fetchClasseMatieres(eleveChoisi!.classe!.id),
    enabled: typeChoisi === 'note' && !!eleveChoisi?.classe,
  })

  const onSubmit = async (values: RevendicationPayload) => {
    setServerError(null)
    try {
      await creerRevendication({
        ...values,
        eleve_id: Number(values.eleve_id),
        classe_matiere_id: values.type === 'note' && values.classe_matiere_id ? Number(values.classe_matiere_id) : null,
        trimestre_id: trimestreActif?.id ?? null,
      })
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={t('revendications.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select label={t('eleves.title')} error={errors.eleve_id?.message} {...register('eleve_id', { required: true })}>
          <option value="">—</option>
          {eleves?.items.map((e) => (
            <option key={e.id} value={e.id}>
              {e.nom_complet} — {e.classe?.nom}
            </option>
          ))}
        </Select>
        <Select label={t('revendications.type')} {...register('type', { required: true })}>
          <option value="note">{t('revendications.type_note')}</option>
          <option value="decision">{t('revendications.type_decision')}</option>
          <option value="autre">{t('revendications.type_autre')}</option>
        </Select>
        {typeChoisi === 'note' && (
          <Select
            label={t('revendications.matiere')}
            error={errors.classe_matiere_id?.message}
            {...register('classe_matiere_id', { required: typeChoisi === 'note' })}
          >
            <option value="">—</option>
            {matieres?.map((cm) => (
              <option key={cm.id} value={cm.id}>
                {cm.matiere.nom}
              </option>
            ))}
          </Select>
        )}
        <Input label={t('revendications.objet')} error={errors.objet?.message} {...register('objet', { required: true })} />
        <Textarea
          label={t('revendications.motif')}
          error={errors.motif?.message}
          {...register('motif', { required: true, minLength: { value: 10, message: t('revendications.motif_min_length') } })}
        />
        <Input label={t('revendications.date_reception')} type="date" {...register('date_reception', { required: true })} />

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

/** Un même geste couvre les trois issues (prise en charge, résolution, rejet) :
 *  la décision motivée n'est exigée par l'API que pour les deux dernières. */
function TraiterModal({
  revendication,
  onClose,
  onTraitee,
}: {
  revendication: Revendication
  onClose: () => void
  onTraitee: () => void
}) {
  const { t } = useTranslation()
  const [statut, setStatut] = useState<StatutRevendication>(revendication.statut === 'en_attente' ? 'en_cours' : 'resolue')
  const [decision, setDecision] = useState(revendication.decision ?? '')
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const decisionRequise = statut === 'resolue' || statut === 'rejetee'

  const submit = async () => {
    setServerError(null)
    setSubmitting(true)
    try {
      await traiterRevendication(revendication.id, { statut, decision: decisionRequise ? decision : null })
      succes(t('revendications.traitee'))
      onTraitee()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={t('revendications.traiter')} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <p className="text-sm text-navy-600">
          <b>{revendication.eleve.nom_complet}</b> — {revendication.objet}
        </p>

        <Select label={t('revendications.statut')} value={statut} onChange={(e) => setStatut(e.target.value as StatutRevendication)}>
          {revendication.statut === 'en_attente' && <option value="en_cours">{t('revendications.statut_en_cours')}</option>}
          <option value="resolue">{t('revendications.statut_resolue')}</option>
          <option value="rejetee">{t('revendications.statut_rejetee')}</option>
        </Select>

        {decisionRequise && (
          <Textarea
            label={t('revendications.decision')}
            value={decision}
            onChange={(e) => setDecision(e.target.value)}
            placeholder={t('revendications.decision_placeholder')}
          />
        )}

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button onClick={submit} disabled={submitting || (decisionRequise && decision.trim().length === 0)}>
            {t('common.save')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
