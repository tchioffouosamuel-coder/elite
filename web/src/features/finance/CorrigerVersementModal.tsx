import { useState } from 'react'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input, MontantInput, Select } from '@/shared/ui/Field'
import { erreur, succes } from '@/shared/lib/alertes'
import { corrigerVersement, MODES, type ModePaiement } from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

/**
 * Corrige un reçu directement depuis la caisse : annule le reçu fautif et
 * ré-encaisse les valeurs corrigées en un seul geste (cf.
 * ScolariteService::corriger()) — pas besoin de ressaisir l'encaissement sur
 * un autre écran.
 */
export function CorrigerVersementModal({
  versement,
  onClose,
  onCorrige,
}: {
  versement: { id: number; numero_recu: string; montant: number; mode: ModePaiement; date_versement: string }
  onClose: () => void
  onCorrige: () => void
}) {
  const [montant, setMontant] = useState<number | null>(versement.montant)
  const [mode, setMode] = useState<ModePaiement>(versement.mode)
  const [dateVersement, setDateVersement] = useState(versement.date_versement)
  const [motif, setMotif] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const corriger = async () => {
    if (!montant) return
    setSubmitting(true)
    try {
      const resultat = await corrigerVersement(versement.id, {
        montant,
        mode,
        date_versement: dateVersement,
        motif: motif || undefined,
      })
      succes(`Reçu corrigé — nouveau reçu ${resultat.numero_recu}.`)
      onCorrige()
      onClose()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={`Corriger le reçu ${versement.numero_recu}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <p className="text-xs text-navy-400">
          Le reçu {versement.numero_recu} sera annulé (contre-passé, jamais effacé) et remplacé par un nouveau reçu
          portant les valeurs ci-dessous.
        </p>

        <MontantInput label="Montant corrigé (F CFA)" value={montant} onChange={setMontant} />

        <label className="flex flex-col gap-1.5">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Mode de paiement</span>
          <Select value={mode} onChange={(e) => setMode(e.target.value as ModePaiement)}>
            {MODES.map((m) => (
              <option key={m.valeur} value={m.valeur}>
                {m.libelle}
              </option>
            ))}
          </Select>
        </label>

        <Input
          type="date"
          label="Date du versement"
          value={dateVersement}
          onChange={(e) => setDateVersement(e.target.value)}
        />
        <Input label="Motif de la correction (optionnel)" value={motif} onChange={(e) => setMotif(e.target.value)} />

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button onClick={corriger} disabled={!montant || submitting}>
            {submitting ? 'Correction…' : 'Corriger le reçu'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
