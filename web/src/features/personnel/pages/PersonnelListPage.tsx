import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, KeyRound, Archive, RotateCcw, Search, FileSpreadsheet, Upload } from 'lucide-react'
import { fetchPersonnels, archivePersonnel, reactivatePersonnel } from '@/features/personnel/api'
import { telechargerFichier } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { Pagination } from '@/shared/ui/Pagination'
import { ImportModal } from '@/shared/ui/ImportModal'
import { PersonnelFormModal } from '@/features/personnel/pages/PersonnelFormModal'
import { CreateAccountModal } from '@/features/personnel/pages/CreateAccountModal'
import { confirmer, succes } from '@/shared/lib/alertes'

export function PersonnelListPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [showForm, setShowForm] = useState(false)
  const [showImport, setShowImport] = useState(false)
  const [accountFor, setAccountFor] = useState<number | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['personnels', { search, page }],
    queryFn: () => fetchPersonnels({ search: search || undefined, page }),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['personnels'] })

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-semibold text-navy-900">{t('personnel.title')}</h1>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={() => telechargerFichier('/personnels/export', undefined, 'personnel.xlsx')}>
            <FileSpreadsheet className="h-4 w-4" />
            {t('export.excel')}
          </Button>
          {can('personnel.manage') && (
            <Button variant="secondary" onClick={() => setShowImport(true)}>
              <Upload className="h-4 w-4" />
              {t('personnel.import')}
            </Button>
          )}
          {can('personnel.manage') && (
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              {t('personnel.add')}
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
              <Th>{t('personnel.nom')}</Th>
              <Th>{t('personnel.fonction')}</Th>
              <Th>{t('personnel.departement')}</Th>
              <Th>{t('personnel.telephone')}</Th>
              <Th>{t('personnel.statut')}</Th>
              <Th>{t('common.actions')}</Th>
            </tr>
          </Thead>
          <tbody>
            {data.items.map((p) => (
              <Tr key={p.id}>
                <Td className="font-medium">{p.nom_complet}</Td>
                <Td>{p.fonction}</Td>
                <Td>{p.departement?.nom ?? '—'}</Td>
                <Td>{p.telephone ?? '—'}</Td>
                <Td>
                  <Badge tone={p.statut === 'actif' ? 'green' : 'neutral'}>{t(`personnel.${p.statut}`)}</Badge>
                </Td>
                <Td>
                  <div className="flex items-center gap-1">
                    {can('personnel.manage') && !p.a_un_compte && (
                      <button
                        title={t('personnel.create_account')}
                        onClick={() => setAccountFor(p.id)}
                        className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700"
                      >
                        <KeyRound className="h-4 w-4" />
                      </button>
                    )}
                    {can('personnel.manage') &&
                      (p.statut === 'actif' ? (
                        <button
                          title={t('common.archive')}
                          onClick={async () => {
                            if (!(await confirmer({
                              titre: `Archiver ${p.nom_complet} ?`,
                              message: "Le compte n'apparaîtra plus dans les listes actives. L'historique est conservé et la réactivation reste possible.",
                              action: 'Archiver',
                            }))) return
                            await archivePersonnel(p.id)
                            invalidate()
                            succes('Personnel archivé.')
                          }}
                          className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
                        >
                          <Archive className="h-4 w-4" />
                        </button>
                      ) : (
                        <button
                          title={t('common.reactivate')}
                          onClick={() => reactivatePersonnel(p.id).then(invalidate)}
                          className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-green-600"
                        >
                          <RotateCcw className="h-4 w-4" />
                        </button>
                      ))}
                  </div>
                </Td>
              </Tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={6}>
                <Pagination pagination={data.pagination} onChange={setPage} />
              </td>
            </tr>
          </tfoot>
        </Table>
      )}

      {showForm && (
        <PersonnelFormModal
          onClose={() => setShowForm(false)}
          onCreated={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}
      {accountFor && (
        <CreateAccountModal
          personnelId={accountFor}
          onClose={() => setAccountFor(null)}
          onCreated={() => {
            setAccountFor(null)
            invalidate()
          }}
        />
      )}
      {showImport && (
        <ImportModal
          title={t('personnel.import')}
          url="/personnels/import"
          columns={['nom', 'prenom', 'fonction', 'matricule', 'telephone', 'email', 'date_embauche']}
          onClose={() => setShowImport(false)}
          onImported={invalidate}
        />
      )}
    </div>
  )
}
