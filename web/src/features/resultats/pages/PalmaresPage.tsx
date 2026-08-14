import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Trophy, FileSpreadsheet, FileDown } from 'lucide-react'
import { fetchPalmares } from '@/features/resultats/api'
import { fetchClasses } from '@/features/classes/api'
import { telechargerFichier, ouvrirDocument } from '@/shared/lib/download'
import { Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'

export function PalmaresPage() {
  const { t } = useTranslation()
  const [classeId, setClasseId] = useState<number | ''>('')

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data, isLoading } = useQuery({
    queryKey: ['palmares', classeId],
    queryFn: () => fetchPalmares(undefined, classeId ? Number(classeId) : undefined),
  })

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Trophy className="h-6 w-6 text-gold-500" />
          <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('resultats.palmares')}</h1>
        </div>
        <div className="flex gap-2">
          <Button
            variant="secondary"
            onClick={() => ouvrirDocument('/palmares/pdf', classeId ? { classe_id: Number(classeId) } : undefined)}
          >
            <FileDown className="h-4 w-4" />
            {t('export.pdf')}
          </Button>
          <Button
            variant="secondary"
            onClick={() =>
              telechargerFichier('/palmares/export', classeId ? { classe_id: Number(classeId) } : undefined, 'palmares.xlsx')
            }
          >
            <FileSpreadsheet className="h-4 w-4" />
            {t('export.excel')}
          </Button>
        </div>
      </div>

      <Select value={classeId} onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')} className="max-w-xs">
        <option value="">{t('common.all')}</option>
        {classes?.map((c) => (
          <option key={c.id} value={c.id}>
            {c.nom}
          </option>
        ))}
      </Select>

      {isLoading ? (
        <Spinner />
      ) : !data || data.eleves.length === 0 ? (
        <Card>
          <EmptyState />
        </Card>
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>#</Th>
              <Th>{t('eleves.nom_complet')}</Th>
              <Th>{t('classes.title')}</Th>
              <Th>{t('resultats.moyenne')}</Th>
            </tr>
          </Thead>
          <tbody>
            {data.eleves.map((e, i) => (
              <Tr key={e.eleve_id}>
                <Td className="font-semibold text-gold-600">{i + 1}</Td>
                <Td className="font-medium">{e.nom_complet}</Td>
                <Td>{e.classe}</Td>
                <Td className="font-semibold">{e.moyenne.toFixed(2)}</Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}
