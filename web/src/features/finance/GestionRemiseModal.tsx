import { useState } from 'react'
import { Pencil, Trash2 } from 'lucide-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input, MontantInput } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { fetchRemises, modifierRemise, supprimerRemise, francs, type RemiseIndividuelle } from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

export function GestionRemiseModal({ eleveId, eleveNom, onClose, onChange }: {
  eleveId: number
  eleveNom: string
  onClose: () => void
  onChange: () => void
}) {
  const queryClient = useQueryClient()
  const [edition, setEdition] = useState<RemiseIndividuelle | null>(null)
  const [montant, setMontant] = useState<number | null>(null)
  const [motif, setMotif] = useState('')
  const { data: remises, isLoading } = useQuery({ queryKey: ['remises', eleveId], queryFn: () => fetchRemises(eleveId) })

  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['remises', eleveId] })
    queryClient.invalidateQueries({ queryKey: ['scolarite-situation'] })
    onChange()
  }

  const ouvrirEdition = (remise: RemiseIndividuelle) => {
    setEdition(remise)
    setMontant(remise.montant)
    setMotif(remise.motif ?? '')
  }

  const enregistrer = async () => {
    if (!edition || !montant) return
    try {
      await modifierRemise(edition.id, { montant, motif: motif || undefined })
      succes('Remise modifiée.')
      setEdition(null)
      rafraichir()
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  const retirer = async (remise: RemiseIndividuelle) => {
    const ok = await confirmer({
      titre: 'Supprimer cette remise ?',
      message: `${francs(remise.montant)} seront ajoutés au montant dû de ${eleveNom}.`,
      action: 'Supprimer',
    })
    if (!ok) return
    try {
      await supprimerRemise(remise.id)
      succes('Remise supprimée.')
      rafraichir()
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  return (
    <Modal title={`Remises — ${eleveNom}`} onClose={onClose}>
      {isLoading ? <Spinner /> : (
        <div className="flex flex-col gap-3">
          {remises?.length === 0 && <p className="text-sm text-navy-400">Aucune remise.</p>}
          {remises?.map((remise) => (
            <div key={remise.id} className="flex items-center justify-between gap-3 rounded-xl border border-navy-100 p-3">
              <div className="min-w-0">
                <div className="font-semibold tabular-nums text-navy-800">{francs(remise.montant)}</div>
                <div className="truncate text-xs text-navy-400">{remise.annee_scolaire ?? 'Année active'}{remise.motif ? ` · ${remise.motif}` : ''}</div>
              </div>
              <div className="flex flex-none gap-1">
                <button type="button" title="Modifier la remise" onClick={() => ouvrirEdition(remise)} className="rounded-lg p-2 text-navy-400 hover:bg-cream-100 hover:text-navy-800">
                  <Pencil className="h-4 w-4" />
                </button>
                <button type="button" title="Supprimer la remise" onClick={() => retirer(remise)} className="rounded-lg p-2 text-navy-300 hover:bg-red-50 hover:text-red-500">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          ))}

          {edition && (
            <div className="flex flex-col gap-2 rounded-xl bg-cream-100 p-3">
              <MontantInput label="Montant de la remise (F CFA)" value={montant} onChange={setMontant} />
              <Input label="Motif (optionnel)" value={motif} onChange={(e) => setMotif(e.target.value)} />
              <div className="flex justify-end gap-2">
                <Button size="sm" variant="secondary" onClick={() => setEdition(null)}>Annuler</Button>
                <Button size="sm" onClick={enregistrer} disabled={!montant}>Enregistrer</Button>
              </div>
            </div>
          )}
        </div>
      )}
    </Modal>
  )
}