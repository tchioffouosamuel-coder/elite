import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { BriefcaseBusiness, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  deleteFonctionReferentiel,
  fetchFonctionsReferentiel,
  type FonctionReferentiel,
} from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { ErrorState, Spinner } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { FonctionReferentielFormModal } from './FonctionReferentielFormModal'

export function FonctionsReferentielPage() {
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [editingFonction, setEditingFonction] = useState<FonctionReferentiel | null>(null)

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
      titre: `Supprimer ${fonction.label_fr} ?`,
      message: 'Cette action est irréversible si la fonction n’est pas utilisée.',
      action: 'Supprimer',
    })
    if (!confirme) return

    try {
      await deleteFonctionReferentiel(fonction.id)
      invalidate()
      succes('Fonction supprimée.')
    } catch (err: any) {
      erreur(err.message || 'Erreur lors de la suppression.')
    }
  }

  const colonnes: Colonne<FonctionReferentiel>[] = [
    {
      cle: 'label_fr',
      entete: 'Libellé français',
      valeur: (f) => f.label_fr,
      cellule: (f) => <span className="font-semibold text-navy-900">{f.label_fr}</span>,
    },
    {
      cle: 'label_en',
      entete: 'Libellé anglais',
      valeur: (f) => f.label_en,
      cellule: (f) => <span className="text-navy-600">{f.label_en ?? '—'}</span>,
    },
    {
      cle: 'personnels_count',
      entete: 'Personnel',
      valeur: (f) => f.personnels_count,
      cellule: (f) => <span className="text-navy-600">{f.personnels_count ?? 0}</span>,
    },
    {
      cle: 'actions',
      entete: 'Actions',
      cellule: (f) => (
        <div className="flex items-center gap-1">
          <button
            title="Modifier"
            onClick={() => setEditingFonction(f)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <Pencil className="h-4 w-4" />
          </button>
          <button
            title="Supprimer"
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
        titre="Référentiel des fonctions"
        icon={BriefcaseBusiness}
        actions={
          <Button onClick={() => setShowForm(true)}>
            <Plus className="h-4 w-4" />
            Créer une fonction
          </Button>
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
          cleLigne={(f) => f.id}
          placeholderRecherche="Rechercher une fonction…"
          messageVide="Aucune fonction définie pour cet établissement."
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
