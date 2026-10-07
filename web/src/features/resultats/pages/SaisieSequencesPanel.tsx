import { useMutation, useQueryClient } from '@tanstack/react-query'
import { LockKeyhole, LockKeyholeOpen } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { modifierSaisieSequence, type Trimestre } from '@/features/pedagogie/api'
import { useAuthStore } from '@/shared/store/authStore'
import { erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

export function SaisieSequencesPanel({ trimestres }: { trimestres: Trimestre[] }) {
  const { t } = useTranslation()
  const user = useAuthStore((s) => s.user)
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const mutation = useMutation<unknown, ApiError, { id: number; ouverte: boolean }>({
    mutationFn: ({ id, ouverte }: { id: number; ouverte: boolean }) => modifierSaisieSequence(id, ouverte),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['trimestres'] }),
        queryClient.invalidateQueries({ queryKey: ['grille-primaire'] }),
      ])
    },
    onError: (err) => erreur(err.message),
  })
  if (!user || user.est_enseignant || !can('trimestres.update')) return null

  const anneeId = trimestres.find((tr) => tr.annee_active)?.annee_scolaire_id
    ?? trimestres.find((tr) => tr.is_active)?.annee_scolaire_id
  const sequences = trimestres.filter((tr) => tr.annee_scolaire_id === anneeId)
    .sort((a, b) => a.ordre - b.ordre)
    .flatMap((tr) => [...tr.sequences].sort((a, b) => a.ordre - b.ordre)
      .map((sequence) => ({ ...sequence, trimestre: tr.libelle })))
  if (sequences.length === 0) return null

  return (
    <section className="border-y border-navy-100 py-3">
      <h2 className="mb-3 text-sm font-semibold text-navy-800">{t('notes.ouverture_saisie')}</h2>
      <div className="flex flex-wrap gap-x-6 gap-y-3">
        {sequences.map((sequence, index) => (
          <label key={sequence.id} className="flex items-center gap-2 text-sm" title={sequence.trimestre}>
            <input
              type="checkbox"
              role="switch"
              aria-label={t('notes.sequence_numero', { numero: index + 1 })}
              checked={sequence.saisie_ouverte}
              disabled={mutation.isPending}
              onChange={(e) => mutation.mutate({ id: sequence.id, ouverte: e.target.checked })}
              className="h-4 w-4 accent-green-600"
            />
            {sequence.saisie_ouverte ? <LockKeyholeOpen className="h-4 w-4 text-green-600" />
              : <LockKeyhole className="h-4 w-4 text-red-500" />}
            <span>{t('notes.sequence_numero', { numero: index + 1 })}</span>
            <span className="text-xs text-navy-500">{t(sequence.saisie_ouverte ? 'notes.ouverte' : 'notes.fermee')}</span>
          </label>
        ))}
      </div>
    </section>
  )
}
