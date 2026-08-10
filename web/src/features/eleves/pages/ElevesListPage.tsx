import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, FileDown, FileSpreadsheet, FileText, IdCard, Upload } from 'lucide-react'
import { fetchEleves } from '@/features/eleves/api'
import { ouvrirBulletin } from '@/features/resultats/api'
import { telechargerFichier, ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { Pagination } from '@/shared/ui/Pagination'
import { ImportModal } from '@/shared/ui/ImportModal'
import { EleveFormModal } from '@/features/eleves/pages/EleveFormModal'

export function ElevesListPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [showForm, setShowForm] = useState(false)
  const [showImport, setShowImport] = useState(false)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['eleves', { search, page }],
    queryFn: () => fetchEleves({ search: search || undefined, page }),
  })

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-semibold text-navy-900">{t('eleves.title')}</h1>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={() => telechargerFichier('/eleves/export', undefined, 'eleves.xlsx')}>
            <FileSpreadsheet className="h-4 w-4" />
            {t('export.excel')}
          </Button>
          {can('eleves.manage') && (
            <Button variant="secondary" onClick={() => setShowImport(true)}>
              <Upload className="h-4 w-4" />
              {t('import.title')}
            </Button>
          )}
          {can('eleves.manage') && (
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              {t('eleves.add')}
            </Button>
          )}
        </div>
      </div>

      <Input
        placeholder={t('common.search')}
        icon={Search}
        value={search}
        onChange={(e) => {
          setSearch(e.target.value)
          setPage(1)
        }}
        className="max-w-xs"
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.items.length === 0 ? (
        <EmptyState />
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>{t('eleves.matricule')}</Th>
              <Th>{t('eleves.nom')}</Th>
              <Th>{t('eleves.sexe')}</Th>
              <Th>{t('eleves.classe')}</Th>
              <Th>{t('eleves.tuteur')}</Th>
              <Th>{t('resultats.bulletin')}</Th>
              <Th>{t('export.attestation')}</Th>
              <Th>{t('export.carte')}</Th>
            </tr>
          </Thead>
          <tbody>
            {data.items.map((e) => (
              <Tr key={e.id}>
                <Td className="font-mono text-xs">{e.matricule ?? '—'}</Td>
                <Td className="font-medium">{e.nom_complet}</Td>
                <Td>
                  <Badge tone={e.sexe === 'F' ? 'gold' : 'neutral'}>
                    {e.sexe === 'F' ? t('eleves.feminin') : t('eleves.masculin')}
                  </Badge>
                </Td>
                <Td>{e.classe?.nom ?? '—'}</Td>
                <Td className="text-navy-500">
                  {e.tuteurs?.[0] ? `${e.tuteurs[0].nom_complet} · ${e.tuteurs[0].telephone ?? '—'}` : '—'}
                </Td>
                <Td>
                  {e.classe && (
                    <button
                      onClick={() => ouvrirBulletin(e.id)}
                      className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
                    >
                      <FileDown className="h-3.5 w-3.5" />
                      PDF
                    </button>
                  )}
                </Td>
                <Td>
                  {e.classe && (
                    <button
                      onClick={() => telechargerFichier(`/eleves/${e.id}/attestation-scolarite`, undefined, 'attestation.docx')}
                      className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
                    >
                      <FileText className="h-3.5 w-3.5" />
                      Word
                    </button>
                  )}
                </Td>
                <Td>
                  {e.classe && (
                    <button
                      onClick={() => ouvrirDocument(`/eleves/${e.id}/carte-scolaire`)}
                      className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
                    >
                      <IdCard className="h-3.5 w-3.5" />
                      PDF
                    </button>
                  )}
                </Td>
              </Tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={8}>
                <Pagination pagination={data.pagination} onChange={setPage} />
              </td>
            </tr>
          </tfoot>
        </Table>
      )}

      {showForm && (
        <EleveFormModal
          onClose={() => setShowForm(false)}
          onCreated={() => {
            setShowForm(false)
            queryClient.invalidateQueries({ queryKey: ['eleves'] })
            queryClient.invalidateQueries({ queryKey: ['classes'] })
          }}
        />
      )}
      {showImport && (
        <ImportModal
          title={t('import.title')}
          url="/eleves/import"
          columns={['nom', 'prenom', 'sexe (M/F)', 'matricule', 'classe', 'date_naissance', 'lieu_naissance', 'tuteur_nom', 'tuteur_prenom', 'tuteur_telephone']}
          onClose={() => setShowImport(false)}
          onImported={() => {
            queryClient.invalidateQueries({ queryKey: ['eleves'] })
            queryClient.invalidateQueries({ queryKey: ['classes'] })
          }}
        />
      )}
    </div>
  )
}
