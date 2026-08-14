import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Layers, Pencil, Plus, Trash2 } from 'lucide-react'
import { fetchSousSystemes } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { batchDeleteNiveaux, batchUpdateNiveaux, deleteNiveau, fetchNiveaux, type Niveau, type NiveauPayload } from '../api'
import { NiveauFormModal } from '../NiveauFormModal'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { ErrorState, Spinner } from '@/shared/ui/Feedback'
import { PageHeader } from '@/shared/ui/PageHeader'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'

export function NiveauxListPage() {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const ecoles = useAuthStore((s) => s.user?.ecoles_accessibles ?? [])
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [selectedNiveau, setSelectedNiveau] = useState<Niveau | null>(null)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [batchSousSystemeId, setBatchSousSystemeId] = useState<string>('')
  const [batchSchoolId, setBatchSchoolId] = useState<string>('')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['niveaux'],
    queryFn: () => fetchNiveaux(),
  })

  const { data: sousSystemes = [] } = useQuery({
    queryKey: ['sous-systemes'],
    queryFn: fetchSousSystemes,
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['niveaux'] })

  const getSousSystemeNom = (niveau: Niveau) => {
    if (!niveau.sous_system_id) return '—'

    const sousSysteme = sousSystemes.find((item) => item.id === niveau.sous_system_id)
    return sousSysteme?.nom ?? '—'
  }

  const handleSubmit = async (_payload: NiveauPayload) => {
    invalidate()
    setIsModalOpen(false)
    setSelectedNiveau(null)
    succes(selectedNiveau ? 'Niveau mis à jour.' : 'Niveau créé.')
  }

  const handleDelete = async (niveau: Niveau) => {
    const ok = await confirmer({
      titre: `Supprimer ${niveau.code} ?`,
      message: 'Cette action est irréversible.',
      action: 'Supprimer',
    })
    if (!ok) return

    try {
      await deleteNiveau(niveau.id)
      invalidate()
      succes('Niveau supprimé.')
    } catch (err: any) {
      erreur(err.message || 'Erreur lors de la suppression.')
    }
  }

  const handleToggleSelect = (id: number) => {
    const newSelectedIds = new Set(selectedIds)
    if (newSelectedIds.has(id)) {
      newSelectedIds.delete(id)
    } else {
      newSelectedIds.add(id)
    }
    setSelectedIds(newSelectedIds)
  }

  const handleSelectAll = () => {
    if (!data) return
    if (selectedIds.size === data.length) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(data.map((n) => n.id)))
    }
  }

  const handleBatchDelete = async () => {
    if (selectedIds.size === 0) return

    const ok = await confirmer({
      titre: `Supprimer ${selectedIds.size} niveau(x) ?`,
      message: 'Cette action est irréversible.',
      action: 'Supprimer',
    })
    if (!ok) return

    try {
      await batchDeleteNiveaux(Array.from(selectedIds))
      invalidate()
      setSelectedIds(new Set())
      succes(`${selectedIds.size} niveau(x) supprimé(s).`)
    } catch (err: any) {
      erreur(err.message || 'Erreur lors de la suppression.')
    }
  }

  const handleBatchAssignSousSysteme = async () => {
    if (selectedIds.size === 0) return

    const ok = await confirmer({
      titre: `Définir le sous-système pour ${selectedIds.size} niveau(x) ?`,
      message: 'Cette action affectera le même sous-système à tous les niveaux sélectionnés.',
      action: 'Appliquer',
    })
    if (!ok) return

    try {
      await batchUpdateNiveaux(
        Array.from(selectedIds),
        batchSousSystemeId ? Number(batchSousSystemeId) : null,
        batchSchoolId ? Number(batchSchoolId) : undefined,
      )
      invalidate()
      setSelectedIds(new Set())
      setBatchSousSystemeId('')
      setBatchSchoolId('')
      succes(`${selectedIds.size} niveau(x) mis à jour.`)
    } catch (err: any) {
      erreur(err.message || 'Erreur lors de la mise à jour.')
    }
  }

  const colonnes: Colonne<Niveau>[] = [
    {
      cle: 'checkbox',
      entete: (
        <input
          type="checkbox"
          checked={data && data.length > 0 && selectedIds.size === data.length}
          onChange={handleSelectAll}
          className="rounded border-navy-300"
        />
      ),
      valeur: () => '',
      cellule: (niveau) => (
        <input
          type="checkbox"
          checked={selectedIds.has(niveau.id)}
          onChange={() => handleToggleSelect(niveau.id)}
          className="rounded border-navy-300"
        />
      ),
      largeur: '50px',
    },
    {
      cle: 'code',
      entete: t('niveaux.code'),
      valeur: (niveau) => niveau.code,
      cellule: (niveau) => <span className="font-mono text-sm font-semibold text-navy-900">{niveau.code}</span>,
    },
    {
      cle: 'name',
      entete: 'Nom',
      valeur: (niveau) => (i18n.language === 'en' ? niveau.name_en : niveau.name_fr),
      cellule: (niveau) => (
        <span className="font-semibold text-navy-900">{i18n.language === 'en' ? niveau.name_en : niveau.name_fr}</span>
      ),
    },
    {
      cle: 'sous_systeme',
      entete: 'Sous-système',
      valeur: (niveau) => getSousSystemeNom(niveau),
      cellule: (niveau) => <span className="text-navy-600">{getSousSystemeNom(niveau)}</span>,
      masquerMobile: true,
    },
    {
      cle: 'school',
      entete: 'École',
      valeur: (niveau) => niveau.school?.name,
      cellule: (niveau) => <span className="text-navy-600">{niveau.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (niveau) => (
        <div className="flex items-center gap-1">
          <button
            title={t('common.edit')}
            onClick={() => {
              setSelectedNiveau(niveau)
              setIsModalOpen(true)
            }}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <Pencil className="h-4 w-4" />
          </button>
          <button
            title={t('common.delete')}
            onClick={() => handleDelete(niveau)}
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
        titre={t('niveaux.title')}
        sousTitre={t('niveaux.hint')}
        icon={Layers}
        actions={
          <div className="flex items-center gap-2">
            {selectedIds.size > 0 && (
              <>
                <select
                  value={batchSousSystemeId}
                  onChange={(e) => setBatchSousSystemeId(e.target.value)}
                  className="rounded-lg border border-navy-200 bg-white px-3 py-2 text-sm text-navy-800 outline-none focus:border-navy-400"
                >
                  <option value="">Sous-système</option>
                  <option value="">Aucun</option>
                  {sousSystemes.map((sousSysteme) => (
                    <option key={sousSysteme.id} value={sousSysteme.id}>
                      {sousSysteme.nom}
                    </option>
                  ))}
                </select>
                <select
                  value={batchSchoolId}
                  onChange={(e) => setBatchSchoolId(e.target.value)}
                  className="rounded-lg border border-navy-200 bg-white px-3 py-2 text-sm text-navy-800 outline-none focus:border-navy-400"
                >
                  <option value="">École</option>
                  <option value="">Aucune</option>
                  {ecoles.map((ecole) => (
                    <option key={ecole.id} value={ecole.id}>
                      {ecole.name}
                    </option>
                  ))}
                </select>
                <Button variant="secondary" onClick={handleBatchAssignSousSysteme} disabled={!batchSousSystemeId && !batchSchoolId}>
                  Appliquer
                </Button>
                <Button variant="secondary" onClick={handleBatchDelete}>
                  <Trash2 className="h-4 w-4" />
                  Supprimer ({selectedIds.size})
                </Button>
              </>
            )}
            <Button
              onClick={() => {
                setSelectedNiveau(null)
                setIsModalOpen(true)
              }}
            >
              <Plus className="h-4 w-4" />
              Créer un niveau
            </Button>
          </div>
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data}
          cleLigne={(niveau) => niveau.id}
          placeholderRecherche="Rechercher un niveau…"
          messageVide={t('niveaux.empty')}
          largeurMin={800}
        />
      )}

      {isModalOpen && (
        <NiveauFormModal
          isOpen={isModalOpen}
          niveau={selectedNiveau}
          onClose={() => {
            setIsModalOpen(false)
            setSelectedNiveau(null)
          }}
          onSubmit={handleSubmit}
        />
      )}
    </div>
  )
}
