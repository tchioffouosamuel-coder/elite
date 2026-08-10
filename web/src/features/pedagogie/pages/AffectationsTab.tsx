import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Plus, Trash2 } from 'lucide-react'
import { fetchClasseMatieres, affecterMatiere, retirerMatiere, fetchMatieres } from '@/features/pedagogie/api'
import type { ClasseMatierePayload } from '@/features/pedagogie/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { confirmerSuppression, succes } from '@/shared/lib/alertes'

export function AffectationsTab({ classeId }: { classeId: number }) {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)

  const { data: affectations, isLoading } = useQuery({
    queryKey: ['classe-matieres', classeId],
    queryFn: () => fetchClasseMatieres(classeId),
  })
  const { data: matieres } = useQuery({ queryKey: ['matieres'], queryFn: fetchMatieres })
  const { data: personnels } = useQuery({
    queryKey: ['personnels', 'all'],
    queryFn: () => fetchPersonnels({ per_page: 100 }),
  })

  const { register, handleSubmit, reset } = useForm<ClasseMatierePayload>({ defaultValues: { coefficient: 1, groupe: 1 } })

  const affectedIds = new Set(affectations?.map((a) => a.matiere.id))
  const matieresDisponibles = matieres?.filter((m) => !affectedIds.has(m.id)) ?? []

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['classe-matieres', classeId] })

  const onSubmit = async (values: ClasseMatierePayload) => {
    await affecterMatiere(classeId, {
      ...values,
      matiere_id: Number(values.matiere_id),
      personnel_id: values.personnel_id ? Number(values.personnel_id) : null,
      coefficient: Number(values.coefficient),
      quota_horaire: values.quota_horaire ? Number(values.quota_horaire) : null,
      groupe: Number(values.groupe) || 1,
    })
    reset()
    setShowForm(false)
    invalidate()
  }

  if (isLoading) return <Spinner />

  return (
    <div className="flex flex-col gap-4">
      {can('pedagogie.manage') && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setShowForm((v) => !v)}>
            <Plus className="h-4 w-4" />
            {t('pedagogie.affecter')}
          </Button>
        </div>
      )}

      {showForm && (
        <form onSubmit={handleSubmit(onSubmit)} className="grid grid-cols-2 gap-3 rounded-xl border border-navy-100 bg-white p-4 sm:grid-cols-4">
          <Select label={t('matieres.title')} {...register('matiere_id', { required: true })}>
            <option value="">—</option>
            {matieresDisponibles.map((m) => (
              <option key={m.id} value={m.id}>
                {m.nom}
              </option>
            ))}
          </Select>
          <Select label={t('pedagogie.enseignant')} {...register('personnel_id')}>
            <option value="">—</option>
            {personnels?.items.map((p) => (
              <option key={p.id} value={p.id}>
                {p.nom_complet}
              </option>
            ))}
          </Select>
          <Input label={t('pedagogie.coefficient')} type="number" step="0.5" {...register('coefficient', { required: true })} />
          <Input label={t('pedagogie.quota_horaire')} type="number" {...register('quota_horaire')} />
          <div className="col-span-2 flex items-end gap-2 sm:col-span-4">
            <Button type="submit" size="sm">
              {t('common.save')}
            </Button>
            <Button type="button" variant="secondary" size="sm" onClick={() => setShowForm(false)}>
              {t('common.cancel')}
            </Button>
          </div>
        </form>
      )}

      {!affectations || affectations.length === 0 ? (
        <EmptyState />
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>{t('matieres.title')}</Th>
              <Th>{t('pedagogie.enseignant')}</Th>
              <Th>{t('pedagogie.coefficient')}</Th>
              <Th>{t('pedagogie.quota_horaire')}</Th>
              {can('pedagogie.manage') && <Th>{t('common.actions')}</Th>}
            </tr>
          </Thead>
          <tbody>
            {affectations.map((a) => (
              <Tr key={a.id}>
                <Td className="font-medium">{a.matiere.nom}</Td>
                <Td>{a.enseignant?.nom_complet ?? '—'}</Td>
                <Td>{a.coefficient}</Td>
                <Td>{a.quota_horaire ?? '—'}</Td>
                {can('pedagogie.manage') && (
                  <Td>
                    <button
                      onClick={async () => {
                        if (!(await confirmerSuppression(`l'affectation de ${a.matiere.nom}`,
                          'Les notes déjà saisies pour cette matière seront également retirées du bulletin.'))) return
                        await retirerMatiere(a.id)
                        invalidate()
                        succes('Affectation retirée.')
                      }}
                      className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </Td>
                )}
              </Tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}
