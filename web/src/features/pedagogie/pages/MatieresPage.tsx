import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { fetchMatieres, createMatiere } from '@/features/pedagogie/api'
import { fetchDepartements } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { useForm } from 'react-hook-form'
import { estSecondaire } from '@/shared/lib/ecole'
import type { MatierePayload } from '@/features/pedagogie/api'

export function MatieresPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['matieres'], queryFn: fetchMatieres })
  const { data: departements } = useQuery({ queryKey: ['departements'], queryFn: fetchDepartements })

  const { register, handleSubmit, reset } = useForm<MatierePayload>()

  // Au primaire une matière est notée sur un barème propre et se découpe en
  // volets ; au secondaire elle est sur 20 et relève d'un département.
  const secondaire = estSecondaire()

  const onSubmit = async (values: MatierePayload) => {
    await createMatiere({
      ...values,
      departement_id: values.departement_id ? Number(values.departement_id) : null,
      notation: values.notation ? Number(values.notation) : null,
    })
    reset()
    setShowForm(false)
    queryClient.invalidateQueries({ queryKey: ['matieres'] })
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('matieres.title')}</h1>
        {can('pedagogie.manage') && (
          <Button onClick={() => setShowForm(true)}>
            <Plus className="h-4 w-4" />
            {t('matieres.add')}
          </Button>
        )}
      </div>

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState />
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>{t('matieres.nom')}</Th>
              <Th>{t('matieres.abbreviation')}</Th>
              {secondaire ? (
                <Th>{t('personnel.departement')}</Th>
              ) : (
                <>
                  <Th>{t('matieres.notation')}</Th>
                  <Th>{t('matieres.volets')}</Th>
                </>
              )}
            </tr>
          </Thead>
          <tbody>
            {data.map((m) => (
              <Tr key={m.id}>
                <Td className="font-medium">{m.nom}</Td>
                <Td>{m.abbreviation ?? '—'}</Td>
                {secondaire ? (
                  <Td>{m.departement?.nom ?? '—'}</Td>
                ) : (
                  <>
                    <Td>{m.notation ? `/ ${m.notation}` : '—'}</Td>
                    <Td className="text-navy-500">{m.composantes.length}</Td>
                  </>
                )}
              </Tr>
            ))}
          </tbody>
        </Table>
      )}

      {showForm && (
        <Modal title={t('matieres.add')} onClose={() => setShowForm(false)}>
          <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
            <Input label={t('matieres.nom')} {...register('nom', { required: true })} />
            <Input label={t('matieres.abbreviation')} {...register('abbreviation')} />
            {secondaire ? (
              <Select label={t('personnel.departement')} {...register('departement_id')}>
                <option value="">—</option>
                {departements?.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.nom}
                  </option>
                ))}
              </Select>
            ) : (
              <>
                <Input label={t('matieres.nom_en')} {...register('nom_en')} />
                <Input
                  type="number"
                  min={10}
                  max={100}
                  label={t('matieres.notation')}
                  {...register('notation', { required: true })}
                />
                <label className="flex items-center gap-2 text-sm text-navy-700">
                  <input type="checkbox" className="h-4 w-4 rounded border-navy-300" {...register('evalue_pratique')} />
                  {t('matieres.evalue_pratique')}
                </label>
              </>
            )}
            <div className="mt-2 flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>
                {t('common.cancel')}
              </Button>
              <Button type="submit">{t('common.save')}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
