import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { fetchClassesForSchool } from '@/features/classes/api'
import { transfererEleveEcole } from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Select } from '@/shared/ui/Field'
import { succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface EleveMinimal {
  id: number
  nom_complet: string
}

export function TransfererEcoleModal({
  eleve,
  onClose,
  onDone,
}: {
  eleve: EleveMinimal
  onClose: () => void
  onDone: () => void
}) {
  const { t } = useTranslation()
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const ecoles = useAuthStore((s) => s.user?.ecoles_accessibles ?? []).filter((e) => e.id !== activeSchoolId)

  const [schoolId, setSchoolId] = useState<number | ''>('')
  const [classeId, setClasseId] = useState<number | ''>('')
  const [submitting, setSubmitting] = useState(false)

  const { data: classes } = useQuery({
    queryKey: ['classes-ecole', schoolId],
    queryFn: () => fetchClassesForSchool(Number(schoolId)),
    enabled: !!schoolId,
  })

  const submit = async () => {
    if (!schoolId || !classeId) return
    setSubmitting(true)
    try {
      await transfererEleveEcole(eleve.id, Number(schoolId), Number(classeId))
      succes(t('eleves.transfered_singular', { nom: eleve.nom_complet }))
      onDone()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={t('eleves.transferer_ecole_titre', { nom: eleve.nom_complet })} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <Select
          label={t('eleves.ecole_destination')}
          value={schoolId}
          onChange={(e) => {
            setSchoolId(e.target.value ? Number(e.target.value) : '')
            setClasseId('')
          }}
        >
          <option value="">—</option>
          {ecoles.map((ecole) => (
            <option key={ecole.id} value={ecole.id}>
              {ecole.name}
            </option>
          ))}
        </Select>

        <Select
          label={t('eleves.classe_destination')}
          value={classeId}
          onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}
          disabled={!schoolId}
        >
          <option value="">—</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </Select>

        <div className="mt-2 flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={submitting} type="button">
            {t('common.cancel')}
          </Button>
          <Button onClick={submit} disabled={submitting || !schoolId || !classeId}>
            {t('eleves.transferer')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
