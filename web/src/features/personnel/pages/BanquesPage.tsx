import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Landmark, Eye, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  deleteBanque,
  batchDeleteBanques,
  fetchBanques,
  type Banque,
} from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'
import { ErrorState, Spinner } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes, info } from '@/shared/lib/alertes'
import { BanqueFormModal } from './BanqueFormModal'

export function BanquesPage() {
  const { t } = useTranslation()
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [editingBanque, setEditingBanque] = useState<Banque | null>(null)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  const { data, isLoading, isError } = useQuery({
    queryKey: ['banques', activeSchoolId],
    queryFn: fetchBanques,
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['banques'] })
    queryClient.invalidateQueries({ queryKey: ['personnels'] })
  }

  const handleDelete = async (banque: Banque) => {
    const confirme = await confirmer({
      titre: t('banques.delete_title', { nom: banque.nom }),
      message: t('banques.delete_message'),
      action: t('common.delete'),
    })
    if (!confirme) return

    try {
      await deleteBanque(banque.id)
      invalidate()
      succes(t('banques.deleted'))
    } catch (err: any) {
      erreur(err.message || t('banques.delete_error'))
    }
  }

  const handleToggleSelect = (id: number) => {
    const newSelected = new Set(selectedIds)
    if (newSelected.has(id)) {
      newSelected.delete(id)
    } else {
      newSelected.add(id)
    }
    setSelectedIds(newSelected)
  }

  const handleSelectAll = (banques: Banque[]) => {
    if (selectedIds.size === banques.length && banques.length > 0) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(banques.map((b) => b.id)))
    }
  }

  const handleBatchDelete = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return

    const confirme = await confirmer({
      titre: t('banques.delete_batch_title', { count: ids.length }),
      message: t('banques.delete_batch_message'),
      action: t('common.delete'),
    })
    if (!confirme) return

    try {
      const { deleted, ignorees } = await batchDeleteBanques(ids)
      setSelectedIds(new Set())
      invalidate()
      if (deleted > 0) succes(t('banques.batch_deleted', { count: deleted }))
      if (ignorees.length > 0) info(t('banques.batch_ignored', { count: ignorees.length, noms: ignorees.join(', ') }))
    } catch (err: any) {
      erreur(err.message || t('banques.delete_error'))
    }
  }

  const colonnes: Colonne<Banque>[] = [
    {
      cle: 'selection',
      entete: data ? (
        <input
          type="checkbox"
          checked={selectedIds.size === data.length && data.length > 0}
          onChange={() => handleSelectAll(data ?? [])}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ) : null,
      cellule: (b) => (
        <input
          type="checkbox"
          checked={selectedIds.has(b.id)}
          onChange={() => handleToggleSelect(b.id)}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ),
    },
    {
      cle: 'nom',
      entete: t('banques.nom'),
      valeur: (b) => b.nom,
      cellule: (b) => <span className="font-semibold text-navy-900">{b.nom}</span>,
    },
    {
      cle: 'code',
      entete: t('banques.code'),
      valeur: (b) => b.code,
      cellule: (b) => <span className="text-navy-600">{b.code ?? '—'}</span>,
    },
    {
      cle: 'personnels_count',
      entete: t('personnel.title'),
      valeur: (b) => b.personnels_count,
      cellule: (b) => <span className="text-navy-600">{b.personnels_count ?? 0}</span>,
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (b) => (
        <div className="flex items-center gap-1">
          <button
            title={t('common.view')}
            onClick={() => navigate(`/banques/${b.id}`)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <Eye className="h-4 w-4" />
          </button>
          <button
            title={t('common.edit')}
            onClick={() => setEditingBanque(b)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <Pencil className="h-4 w-4" />
          </button>
          <button
            title={t('common.delete')}
            onClick={() => handleDelete(b)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
          >
            <Trash2 className="h-4 w-4" />
          </button>
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('banques.title')}
        icon={Landmark}
        actions={
          <div className="flex items-center gap-2">
            <ImportExportBar
              titreImport={t('banques.title')}
              importUrl="/banques/import"
              exportUrl="/banques/export"
              modeleUrl="/banques/modele"
              colonnes={['Banque', 'Code / SWIFT']}
              nomFichier="banques"
              onImported={() => queryClient.invalidateQueries({ queryKey: ['banques'] })}
            />
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              {t('banques.create')}
            </Button>
          </div>
        }
      />

      {selectedIds.size > 0 && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="font-medium text-navy-900">{t('banques.selected_count', { count: selectedIds.size })}</p>
            <div className="flex flex-wrap gap-2">
              <Button variant="danger" onClick={handleBatchDelete}>
                <Trash2 className="h-4 w-4" />
                {t('common.delete')}
              </Button>
              <button
                onClick={() => setSelectedIds(new Set())}
                className="rounded-lg px-4 py-2 text-sm font-medium text-navy-600 hover:bg-navy-50 whitespace-nowrap"
              >
                {t('common.cancel')}
              </button>
            </div>
          </div>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data}
          cleLigne={(b) => b.id}
          placeholderRecherche={t('banques.search_placeholder')}
          messageVide={t('banques.empty')}
          largeurMin={760}
        />
      )}

      {showForm && (
        <BanqueFormModal
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}
      {editingBanque && (
        <BanqueFormModal
          banque={editingBanque}
          onClose={() => setEditingBanque(null)}
          onSaved={() => {
            setEditingBanque(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}
