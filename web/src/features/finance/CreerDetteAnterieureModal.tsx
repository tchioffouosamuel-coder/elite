import { useState } from 'react'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input, MontantInput } from '@/shared/ui/Field'
import { Select } from '@/shared/ui/Select'
import { erreur, succes } from '@/shared/lib/alertes'
import { creerDetteAnterieure } from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

interface EleveOption {
  id: number
  nom_complet: string
  matricule: string | null
}

/**
 * Enregistre une dette antérieure directement depuis la caisse, sans passer
 * par la liste des insolvables — la famille n'y apparaît pas forcément
 * encore (dette héritée d'avant le système, élève qui n'a pas de reste dû
 * par ailleurs) et l'économe doit pouvoir la saisir dès qu'il en a besoin.
 */
export function CreerDetteAnterieureModal({
  eleves,
  onClose,
  onCreated,
}: {
  eleves: EleveOption[]
  onClose: () => void
  onCreated: () => void
}) {
  const [eleveId, setEleveId] = useState<number | null>(null)
  const [montant, setMontant] = useState<number | null>(null)
  const [motif, setMotif] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const options = eleves.map((e) => ({ value: e.id, label: `${e.nom_complet}${e.matricule ? ` · ${e.matricule}` : ''}` }))

  const enregistrer = async () => {
    if (!eleveId || !montant) return
    setSubmitting(true)
    try {
      await creerDetteAnterieure(eleveId, { montant, motif: motif || undefined })
      succes('Dette antérieure enregistrée.')
      onCreated()
      onClose()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title="Enregistrer une dette antérieure" onClose={onClose}>
      <div className="flex flex-col gap-3">
        <p className="text-xs text-navy-400">
          Une dette héritée d'avant l'usage de ce système. Elle s'ajoute automatiquement au dossier de l'élève dès qu'il en a un pour
          l'année active, et apparaît comme rubrique « Reliquat » sur la liste des insolvables.
        </p>

        <label className="flex flex-col gap-1.5">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Élève</span>
          <Select
            options={options}
            value={options.find((o) => o.value === eleveId) ?? null}
            onChange={(option) => setEleveId(option ? Number(option.value) : null)}
            placeholder="Rechercher un élève…"
            isClearable
          />
        </label>

        <MontantInput label="Montant de la dette (F CFA)" value={montant} onChange={setMontant} />
        <Input label="Motif (optionnel)" value={motif} onChange={(e) => setMotif(e.target.value)} />

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button onClick={enregistrer} disabled={!eleveId || !montant || submitting}>
            {submitting ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
