import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ShieldCheck, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  deleteRegleValidationSeance,
  fetchReglesValidationSeance,
  type RegleValidationSeance,
} from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { ErrorState, Spinner } from '@/shared/ui/Feedback'
import { triEcoleSousSystemeNiveauClasse } from '@/shared/lib/triHierarchique'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { RegleValidationSeanceFormModal } from './RegleValidationSeanceFormModal'

const LIBELLES_METHODE: Record<RegleValidationSeance['methode_validation'], string> = {
  qr: 'QR code de la salle',
  code: 'Code court de la salle',
  libre: 'Libre (aucune preuve)',
}

const LIBELLES_UNITE: Record<RegleValidationSeance['delai_unite'], string> = {
  minutes: 'min',
  jours: 'j',
  semaines: 'sem.',
}

/**
 * Règles par défaut de preuve de présence pour « Ma journée » (méthode +
 * délai avant blocage de l'appel), définies par école et, en option, par
 * sous-système. Une fiche de personnel peut toujours la surcharger
 * individuellement depuis son propre formulaire.
 */
export function ReglesValidationSeancePage() {
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<RegleValidationSeance | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['regles-validation-seance', activeSchoolId],
    queryFn: fetchReglesValidationSeance,
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['regles-validation-seance'] })

  const handleDelete = async (regle: RegleValidationSeance) => {
    const confirme = await confirmer({
      titre: 'Supprimer cette règle ?',
      message: "L'école reviendra au comportement par défaut (QR, 15 minutes) pour ce périmètre.",
      action: 'Supprimer',
    })
    if (!confirme) return

    try {
      await deleteRegleValidationSeance(regle.id)
      invalidate()
      succes('Règle supprimée.')
    } catch (err: any) {
      erreur(err.message || 'Suppression impossible.')
    }
  }

  const triRegles = triEcoleSousSystemeNiveauClasse<RegleValidationSeance>({
    sousSysteme: (r) => r.sous_systeme,
  })

  const colonnes: Colonne<RegleValidationSeance>[] = [
    {
      cle: 'perimetre',
      entete: 'Sous-système',
      valeur: (r) => r.sous_systeme,
      cellule: (r) => <span className="font-semibold text-navy-900">{r.sous_systeme ?? 'Toute l\'école'}</span>,
    },
    {
      cle: 'methode',
      entete: 'Mode de validation',
      valeur: (r) => r.methode_validation,
      cellule: (r) => <span className="text-navy-600">{LIBELLES_METHODE[r.methode_validation]}</span>,
    },
    {
      cle: 'delai',
      entete: 'Délai avant blocage',
      valeur: (r) => r.delai_en_minutes,
      cellule: (r) => (
        <span className="text-navy-600">
          {r.delai_valeur} {LIBELLES_UNITE[r.delai_unite]}
        </span>
      ),
    },
    {
      cle: 'actions',
      entete: 'Actions',
      cellule: (r) => (
        <div className="flex items-center gap-1">
          <button
            title="Modifier"
            onClick={() => setEditing(r)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <Pencil className="h-4 w-4" />
          </button>
          <button
            title="Supprimer"
            onClick={() => handleDelete(r)}
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
        titre="Validation des séances"
        sousTitre="Mode de preuve de présence et délai de correction de l'appel pour « Ma journée », par école et sous-système."
        icon={ShieldCheck}
        actions={
          <Button onClick={() => setShowForm(true)}>
            <Plus className="h-4 w-4" />
            Nouvelle règle
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
          cleLigne={(r) => r.id}
          messageVide="Aucune règle définie — le comportement par défaut (QR, 15 minutes) s'applique partout."
          largeurMin={640}
          triDefaut={triRegles}
        />
      )}

      {showForm && (
        <RegleValidationSeanceFormModal
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}
      {editing && (
        <RegleValidationSeanceFormModal
          regle={editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}
