import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { BriefcaseBusiness, Eye, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  deleteFonctionReferentiel,
  batchDeleteFonctionsReferentiel,
  fetchFonctionsReferentiel,
  type FonctionReferentiel,
} from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { ErrorState, Spinner } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes, info } from '@/shared/lib/alertes'
import { FonctionReferentielFormModal } from './FonctionReferentielFormModal'

export function FonctionsReferentielPage() {
  const { t } = useTranslation()
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [editingFonction, setEditingFonction] = useState<FonctionReferentiel | null>(null)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  const { data, isLoading, isError } = useQuery({
    queryKey: ['fonctions-referentiel', activeSchoolId],
    queryFn: fetchFonctionsReferentiel,
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['fonctions-referentiel'] })
    queryClient.invalidateQueries({ queryKey: ['personnels'] })
  }

  const handleDelete = async (fonction: FonctionReferentiel) => {
    const confirme = await confirmer({
      titre: t('fonctionsReferentiel.delete_title', { label: fonction.label_fr }),
      message: t('fonctionsReferentiel.delete_message'),
      action: t('common.delete'),
    })
    if (!confirme) return

    try {
      await deleteFonctionReferentiel(fonction.id)
      invalidate()
      succes(t('fonctionsReferentiel.deleted'))
    } catch (err: any) {
      erreur(err.message || t('fonctionsReferentiel.delete_error'))
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

  const handleSelectAll = (fonctions: FonctionReferentiel[]) => {
    if (selectedIds.size === fonctions.length && fonctions.length > 0) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(fonctions.map((f) => f.id)))
    }
  }

  const handleBatchDelete = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return

    const confirme = await confirmer({
      titre: t('fonctionsReferentiel.delete_batch_title', { count: ids.length }),
      message: t('fonctionsReferentiel.delete_batch_message'),
      action: t('common.delete'),
    })
    if (!confirme) return

    try {
      const { deleted, ignorees } = await batchDeleteFonctionsReferentiel(ids)
      setSelectedIds(new Set())
      invalidate()
      if (deleted > 0) succes(t('fonctionsReferentiel.batch_deleted', { count: deleted }))
      if (ignorees.length > 0) info(t('fonctionsReferentiel.batch_ignored', { count: ignorees.length, noms: ignorees.join(', ') }))
    } catch (err: any) {
      erreur(err.message || t('fonctionsReferentiel.delete_error'))
    }
  }

  const colonnes: Colonne<FonctionReferentiel>[] = [
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
      cellule: (f) => (
        <input
          type="checkbox"
          checked={selectedIds.has(f.id)}
          onChange={() => handleToggleSelect(f.id)}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ),
    },
    {
      cle: 'label_fr',
      entete: t('fonctionsReferentiel.label_fr'),
      valeur: (f) => f.label_fr,
      cellule: (f) => <span className="font-semibold text-navy-900">{f.label_fr}</span>,
    },
    {
      cle: 'label_en',
      entete: t('fonctionsReferentiel.label_en'),
      valeur: (f) => f.label_en,
      cellule: (f) => <span className="text-navy-600">{f.label_en ?? '—'}</span>,
    },
    {
      cle: 'personnels_count',
      entete: t('personnel.title'),
      valeur: (f) => f.personnels_count,
      cellule: (f) => <span className="text-navy-600">{f.personnels_count ?? 0}</span>,
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (f) => (
        <div className="flex items-center gap-1">
          <button
            title={t('common.view')}
            onClick={() => navigate(`/fonctions-referentiel/${f.id}`)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <Eye className="h-4 w-4" />
          </button>
          <button
            title={t('common.edit')}
            onClick={() => setEditingFonction(f)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <Pencil className="h-4 w-4" />
          </button>
          <button
            title={t('common.delete')}
            onClick={() => handleDelete(f)}
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
        titre={t('fonctionsReferentiel.title')}
        icon={BriefcaseBusiness}
        actions={
          <Button onClick={() => setShowForm(true)}>
            <Plus className="h-4 w-4" />
            {t('fonctionsReferentiel.create')}
          </Button>
        }
      />

      {selectedIds.size > 0 && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="font-medium text-navy-900">{t('fonctionsReferentiel.selected_count', { count: selectedIds.size })}</p>
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
          cleLigne={(f) => f.id}
          placeholderRecherche={t('fonctionsReferentiel.search_placeholder')}
          messageVide={t('fonctionsReferentiel.empty')}
          largeurMin={760}
        />
      )}

      {showForm && (
        <FonctionReferentielFormModal
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}
      {editingFonction && (
        <FonctionReferentielFormModal
          fonction={editingFonction}
          onClose={() => setEditingFonction(null)}
          onSaved={() => {
            setEditingFonction(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}
