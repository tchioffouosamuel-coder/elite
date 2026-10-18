import { useState } from 'react'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input, MontantInput } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import {
  creerDetteAnterieure,
  fetchDettesAnterieures,
  modifierDetteAnterieure,
  supprimerDetteAnterieure,
  francs,
  type DetteAnterieure,
} from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

/**
 * Gestion des dettes antérieures d'un élève directement depuis la caisse —
 * même patron que `GestionRemiseModal` : liste, édition, suppression et
 * création, sans quitter l'écran pour passer par la page dédiée.
 */
export function GestionDetteAnterieureModal({ eleveId, eleveNom, onClose, onChange }: {
  eleveId: number
  eleveNom: string
  onClose: () => void
  onChange: () => void
}) {
  const queryClient = useQueryClient()
  const [edition, setEdition] = useState<DetteAnterieure | 'nouvelle' | null>(null)
  const [montant, setMontant] = useState<number | null>(null)
  const [motif, setMotif] = useState('')
  const { data: dettes, isLoading } = useQuery({
    queryKey: ['dettes-anterieures', eleveId],
    queryFn: () => fetchDettesAnterieures(eleveId),
  })

  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['dettes-anterieures', eleveId] })
    queryClient.invalidateQueries({ queryKey: ['scolarite-situation'] })
    onChange()
  }

  const ouvrirEdition = (dette: DetteAnterieure) => {
    setEdition(dette)
    setMontant(dette.montant)
    setMotif(dette.motif ?? '')
  }

  const ouvrirCreation = () => {
    setEdition('nouvelle')
    setMontant(null)
    setMotif('')
  }

  const enregistrer = async () => {
    if (!edition || !montant) return
    try {
      if (edition === 'nouvelle') {
        await creerDetteAnterieure(eleveId, { montant, motif: motif || undefined })
        succes('Dette antérieure enregistrée.')
      } else {
        await modifierDetteAnterieure(edition.id, { montant, motif: motif || undefined })
        succes('Dette antérieure modifiée.')
      }
      setEdition(null)
      rafraichir()
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  const retirer = async (dette: DetteAnterieure) => {
    const ok = await confirmer({
      titre: 'Supprimer cette dette ?',
      message: `${francs(dette.montant)} seront retirés du reliquat de ${eleveNom}.`,
      action: 'Supprimer',
    })
    if (!ok) return
    try {
      await supprimerDetteAnterieure(dette.id)
      succes('Dette antérieure supprimée.')
      rafraichir()
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  return (
    <Modal title={`Dettes antérieures — ${eleveNom}`} onClose={onClose}>
      {isLoading ? <Spinner /> : (
        <div className="flex flex-col gap-3">
          {dettes?.length === 0 && <p className="text-sm text-navy-400">Aucune dette antérieure.</p>}
          {dettes?.map((dette) => (
            <div key={dette.id} className="flex items-center justify-between gap-3 rounded-xl border border-navy-100 p-3">
              <div className="min-w-0">
                <div className="font-semibold tabular-nums text-navy-800">{francs(dette.montant)}</div>
                <div className="truncate text-xs text-navy-400">
                  {dette.imputee ? 'Déjà reprise dans le dossier' : 'Pas encore reprise'}
                  {dette.motif ? ` · ${dette.motif}` : ''}
                </div>
              </div>
              <div className="flex flex-none gap-1">
                <button type="button" title="Modifier la dette" onClick={() => ouvrirEdition(dette)} className="rounded-lg p-2 text-navy-400 hover:bg-cream-100 hover:text-navy-800">
                  <Pencil className="h-4 w-4" />
                </button>
                {!dette.imputee && (
                  <button type="button" title="Supprimer la dette" onClick={() => retirer(dette)} className="rounded-lg p-2 text-navy-300 hover:bg-red-50 hover:text-red-500">
                    <Trash2 className="h-4 w-4" />
                  </button>
                )}
              </div>
            </div>
          ))}

          {edition && (
            <div className="flex flex-col gap-2 rounded-xl bg-cream-100 p-3">
              <MontantInput label="Montant de la dette (F CFA)" value={montant} onChange={setMontant} />
              <Input label="Motif (optionnel)" value={motif} onChange={(e) => setMotif(e.target.value)} />
              <div className="flex justify-end gap-2">
                <Button size="sm" variant="secondary" onClick={() => setEdition(null)}>Annuler</Button>
                <Button size="sm" onClick={enregistrer} disabled={!montant}>Enregistrer</Button>
              </div>
            </div>
          )}

          {!edition && (
            <Button size="sm" variant="secondary" onClick={ouvrirCreation}>
              <Plus className="h-3.5 w-3.5" />
              Enregistrer une dette antérieure
            </Button>
          )}
        </div>
      )}
    </Modal>
  )
}
