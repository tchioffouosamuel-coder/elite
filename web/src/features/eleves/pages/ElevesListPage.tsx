import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, FileDown, FileSpreadsheet, FileText, Camera, Upload } from 'lucide-react'
import { fetchEleves, uploadElevePhoto } from '@/features/eleves/api'
import { ouvrirBulletin } from '@/features/resultats/api'
import { telechargerFichier } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { Pagination } from '@/shared/ui/Pagination'
import { ImportModal } from '@/shared/ui/ImportModal'
import { EleveFormModal } from '@/features/eleves/pages/EleveFormModal'

function PhotoCell({ eleve, canManage }: { eleve: { id: number; nom_complet: string; photo_url: string | null }; canManage: boolean }) {
  const queryClient = useQueryClient()
  const inputRef = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)

  const handleFile = async (file: File | undefined) => {
    if (!file) return
    setUploading(true)
    try {
      await uploadElevePhoto(eleve.id, file)
      queryClient.invalidateQueries({ queryKey: ['eleves'] })
    } finally {
      setUploading(false)
    }
  }

  return (
    <div className="relative h-10 w-10 flex-none">
      {eleve.photo_url ? (
        <img src={eleve.photo_url} alt={eleve.nom_complet} className="h-10 w-10 rounded-full object-cover ring-1 ring-navy-100" />
      ) : (
        <span className="flex h-10 w-10 items-center justify-center rounded-full bg-navy-700 text-xs font-bold text-cream-50">
          {eleve.nom_complet
            .split(' ')
            .map((p) => p[0])
            .slice(0, 2)
            .join('')
            .toUpperCase()}
        </span>
      )}
      {canManage && (
        <>
          <button
            type="button"
            onClick={() => inputRef.current?.click()}
            disabled={uploading}
            title="Photo"
            className="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-gold-500 text-navy-900 shadow-soft hover:bg-gold-600"
          >
            <Camera className="h-3 w-3" />
          </button>
          <input
            ref={inputRef}
            type="file"
            accept="image/jpeg,image/jpg,image/png"
            className="hidden"
            onChange={(e) => handleFile(e.target.files?.[0])}
          />
        </>
      )}
    </div>
  )
}

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
              <Th></Th>
              <Th>{t('eleves.matricule')}</Th>
              <Th>{t('eleves.nom')}</Th>
              <Th>{t('eleves.sexe')}</Th>
              <Th>{t('eleves.classe')}</Th>
              <Th>{t('eleves.tuteur')}</Th>
              <Th>{t('resultats.bulletin')}</Th>
              <Th>{t('export.attestation')}</Th>
            </tr>
          </Thead>
          <tbody>
            {data.items.map((e) => (
              <Tr key={e.id}>
                <Td>
                  <PhotoCell eleve={e} canManage={can('eleves.manage')} />
                </Td>
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
