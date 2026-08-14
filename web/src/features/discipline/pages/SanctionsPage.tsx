import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Plus, Trash2 } from 'lucide-react'
import { fetchSanctions, createSanction, deleteSanction } from '@/features/discipline/api'
import type { Sanction, SanctionPayload } from '@/features/discipline/api'
import { fetchClasses } from '@/features/classes/api'
import { fetchEleves } from '@/features/eleves/api'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Badge } from '@/shared/ui/Badge'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmerSuppression, succes } from '@/shared/lib/alertes'

const TYPE_TONE: Record<string, 'gold' | 'red' | 'neutral'> = {
  corvee: 'gold',
  exclusion_temporaire: 'red',
  exclusion_definitive: 'red',
  autre: 'neutral',
}

export function SanctionsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [classeFiltre, setClasseFiltre] = useState<number | ''>('')
  const [showForm, setShowForm] = useState(false)

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: sanctions, isLoading } = useQuery({
    queryKey: ['sanctions', classeFiltre],
    queryFn: () => fetchSanctions(classeFiltre ? { classe_id: Number(classeFiltre) } : {}),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['sanctions'] })

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
    ...(can('discipline.manage')
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (s: Sanction) => (
              <button
                onClick={async () => {
                  if (!(await confirmerSuppression(`la sanction de ${s.eleve.nom_complet}`))) return
                  await deleteSanction(s.id)
                  invalidate()
                  succes('Sanction supprimée.')
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
              >
                <Trash2 className="h-4 w-4" />
              </button>
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
          <Button onClick={() => setShowForm(true)}>
            <Plus className="h-4 w-4" />
            {t('discipline.add_sanction')}
          </Button>
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
          placeholderRecherche="Rechercher un élève, un motif…"
          messageVide="Aucune sanction enregistrée."
          largeurMin={760}
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
  const { data: eleves } = useQuery({ queryKey: ['eleves', 'all'], queryFn: () => fetchEleves({ per_page: 200 }) })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]

  const { register, handleSubmit } = useForm<SanctionPayload>({
    defaultValues: { type: 'corvee', date_sanction: new Date().toISOString().slice(0, 10) },
  })

  const onSubmit = async (values: SanctionPayload) => {
    await createSanction({
      ...values,
      eleve_id: Number(values.eleve_id),
      trimestre_id: trimestreActif ? trimestreActif.id : Number(values.trimestre_id),
      duree_jours: values.duree_jours ? Number(values.duree_jours) : null,
    })
    onCreated()
  }

  return (
    <Modal title={t('discipline.add_sanction')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select label={t('eleves.title')} {...register('eleve_id', { required: true })}>
          <option value="">—</option>
          {eleves?.items.map((e) => (
            <option key={e.id} value={e.id}>
              {e.nom_complet} — {e.classe?.nom}
            </option>
          ))}
        </Select>
        <Select label={t('discipline.type')} {...register('type', { required: true })}>
          <option value="corvee">{t('discipline.type_corvee')}</option>
          <option value="exclusion_temporaire">{t('discipline.type_exclusion_temporaire')}</option>
          <option value="exclusion_definitive">{t('discipline.type_exclusion_definitive')}</option>
          <option value="autre">{t('discipline.type_autre')}</option>
        </Select>
        <div className="grid grid-cols-2 gap-3">
          <Input label={t('discipline.duree_jours')} type="number" min={1} {...register('duree_jours')} />
          <Input label={t('discipline.date')} type="date" {...register('date_sanction', { required: true })} />
        </div>
        <Input label={t('discipline.motif')} {...register('motif', { required: true })} />

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit">{t('common.save')}</Button>
        </div>
      </form>
    </Modal>
  )
}
