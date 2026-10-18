import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { Ban, Pencil, Receipt } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Badge } from '@/shared/ui/Badge'
import { ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { annulerVersement, francs, type DossierScolarite, type ModePaiement } from '@/features/finance/api'
import { CorrigerVersementModal } from '@/features/finance/CorrigerVersementModal'
import type { ApiError } from '@/shared/types/api'

type Versement = NonNullable<DossierScolarite['versements']>[number]

/**
 * Historique complet des paiements d'un élève : le bouton « Annuler »
 * n'agissait jusque-là que sur le dernier reçu — s'il y en a plusieurs, il
 * fallait deviner lequel corriger. Cette fenêtre liste tous les versements et
 * porte l'annulation/correction au niveau de la ligne concernée.
 */
export function HistoriqueVersementsModal({ eleveNom, versements, onClose, onChange }: {
  eleveNom: string
  versements: Versement[]
  onClose: () => void
  onChange: () => void
}) {
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [versementACorriger, setVersementACorriger] = useState<Versement | null>(null)

  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['scolarite-situation'] })
    onChange()
  }

  const annuler = async (versement: Versement) => {
    const ok = await confirmer({
      titre: `Annuler le reçu ${versement.numero_recu} ?`,
      message: `${francs(versement.montant)} seront retirés du solde de ${eleveNom}. Le reçu reste au registre, marqué annulé.`,
      action: 'Annuler le reçu',
    })
    if (!ok) return

    try {
      await annulerVersement(versement.id, 'Annulation depuis la caisse')
      succes('Reçu annulé.')
      rafraichir()
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  const tries = [...versements].sort((a, b) => b.created_at.localeCompare(a.created_at))

  return (
    <>
      <Modal title={`Historique des paiements — ${eleveNom}`} onClose={onClose}>
        {tries.length === 0 ? (
          <p className="text-sm text-navy-400">Aucun paiement enregistré.</p>
        ) : (
          <div className="flex flex-col divide-y divide-navy-50">
            {tries.map((v) => (
              <div key={v.id} className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className={`font-semibold tabular-nums ${v.annule ? 'text-navy-300 line-through' : 'text-navy-800'}`}>
                      {francs(v.montant)}
                    </span>
                    {v.annule && <Badge tone="neutral">Annulé</Badge>}
                  </div>
                  <div className="truncate text-xs text-navy-400">
                    Reçu {v.numero_recu} · {new Date(v.date_versement).toLocaleDateString('fr-FR')} · {v.cree_par}
                  </div>
                  {v.annule && (
                    <div className="truncate text-xs text-red-400">
                      Annulé par {v.annule_par ?? '—'} le {v.annule_le ? new Date(v.annule_le).toLocaleDateString('fr-FR') : '—'}
                      {v.motif_annulation ? ` · ${v.motif_annulation}` : ''}
                    </div>
                  )}
                </div>
                <div className="flex flex-none gap-1">
                  <button
                    type="button"
                    title={`Reçu ${v.numero_recu}`}
                    onClick={() => ouvrirDocument(`/versements/${v.id}/recu`)}
                    className="rounded-lg p-2 text-navy-400 hover:bg-cream-100 hover:text-navy-800"
                  >
                    <Receipt className="h-4 w-4" />
                  </button>
                  {!v.annule && can('finance.annuler') && can('finance.encaisser') && (
                    <button
                      type="button"
                      title="Corriger ce reçu"
                      onClick={() => setVersementACorriger(v)}
                      className="rounded-lg p-2 text-navy-400 hover:bg-cream-100 hover:text-navy-800"
                    >
                      <Pencil className="h-4 w-4" />
                    </button>
                  )}
                  {!v.annule && can('finance.annuler') && (
                    <button
                      type="button"
                      title="Annuler ce reçu"
                      onClick={() => annuler(v)}
                      className="rounded-lg p-2 text-navy-300 hover:bg-red-50 hover:text-red-500"
                    >
                      <Ban className="h-4 w-4" />
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </Modal>

      {versementACorriger && (
        <CorrigerVersementModal
          versement={{
            id: versementACorriger.id,
            numero_recu: versementACorriger.numero_recu,
            montant: versementACorriger.montant,
            mode: versementACorriger.mode as ModePaiement,
            date_versement: versementACorriger.date_versement,
          }}
          onClose={() => setVersementACorriger(null)}
          onCorrige={() => {
            setVersementACorriger(null)
            rafraichir()
          }}
        />
      )}
    </>
  )
}
