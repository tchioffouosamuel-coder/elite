import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { fetchDepartements, createDepartement } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'

export function DepartementsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const { data, isLoading, isError } = useQuery({ queryKey: ['departements'], queryFn: fetchDepartements })
  const [nom, setNom] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const handleAdd = async () => {
    if (!nom.trim()) return
    setSubmitting(true)
    try {
      await createDepartement(nom.trim())
      setNom('')
      queryClient.invalidateQueries({ queryKey: ['departements'] })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <h1 className="font-display text-2xl font-semibold text-navy-900">{t('nav.departements')}</h1>

      {can('personnel.manage') && (
        <div className="flex max-w-md gap-2">
          <Input value={nom} onChange={(e) => setNom(e.target.value)} placeholder={t('personnel.departement')} />
          <Button onClick={handleAdd} disabled={submitting}>
            <Plus className="h-4 w-4" />
            {t('common.add')}
          </Button>
        </div>
      )}

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
              <Th>{t('personnel.departement')}</Th>
            </tr>
          </Thead>
          <tbody>
            {data.map((d) => (
              <Tr key={d.id}>
                <Td>{d.nom}</Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}
