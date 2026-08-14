import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchClasses } from '@/features/classes/api'
import { changerClasseEleve } from '@/features/eleves/api'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Select } from '@/shared/ui/Field'
import { succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface EleveMinimal {
  id: number
  nom_complet: string
  classe: { id: number; nom: string } | null
}

export function TransfererClasseModal({
  eleve,
  onClose,
  onDone,
}: {
  eleve: EleveMinimal
  onClose: () => void
  onDone: () => void
}) {
  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const [classeId, setClasseId] = useState<number | ''>(eleve.classe?.id ?? '')
  const [submitting, setSubmitting] = useState(false)

  const submit = async () => {
    if (!classeId) return
    setSubmitting(true)
    try {
      await changerClasseEleve(eleve.id, Number(classeId))
      succes(`${eleve.nom_complet} transféré(e).`)
      onDone()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={`Changer la classe de ${eleve.nom_complet}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <Select label="Classe de destination" value={classeId} onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}>
          <option value="">—</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </Select>

        <div className="mt-2 flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={submitting} type="button">
            Annuler
          </Button>
          <Button onClick={submit} disabled={submitting || !classeId || classeId === eleve.classe?.id}>
            Transférer
          </Button>
        </div>
      </div>
    </Modal>
  )
}
