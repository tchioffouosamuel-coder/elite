import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { succes, erreur } from '@/shared/lib/alertes'
import { fetchCiblesCopieProgression, copierProgression } from '@/features/progression/api'
import type { ApiError } from '@/shared/types/api'

/**
 * Copie les leçons d'une matière d'une classe vers une ou plusieurs autres
 * classes où la même matière est aussi enseignée — demande du client,
 * distincte du partage automatique par enseignant+niveau (point 1 de la
 * punch-list) : utile quand ce sont des enseignants différents.
 */
export function CopierProgressionModal({
  classeMatiereId,
  matiereNom,
  onClose,
  onCopie,
}: {
  classeMatiereId: number
  matiereNom: string
  onClose: () => void
  onCopie: () => void
}) {
  const [selection, setSelection] = useState<Set<number>>(new Set())
  const [envoi, setEnvoi] = useState(false)

  const { data: cibles, isLoading } = useQuery({
    queryKey: ['progression-cibles-copie', classeMatiereId],
    queryFn: () => fetchCiblesCopieProgression(classeMatiereId),
  })

  const basculer = (id: number) =>
    setSelection((courante) => {
      const suivante = new Set(courante)
      if (suivante.has(id)) suivante.delete(id)
      else suivante.add(id)
      return suivante
    })

  const copier = async () => {
    if (selection.size === 0) return
    setEnvoi(true)
    try {
      const resultat = await copierProgression(classeMatiereId, [...selection])
      succes(
        resultat.copiees.length > 0
          ? `Fiche copiée vers : ${resultat.copiees.join(', ')}.`
          : 'Aucune classe copiée.',
      )
      if (resultat.ignorees.length > 0) {
        erreur(`Ignorée(s) car déjà une fiche : ${resultat.ignorees.join(', ')}.`)
      }
      onCopie()
      onClose()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={`Copier la fiche — ${matiereNom}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <p className="text-xs text-navy-400">
          Les leçons et colonnes de cette fiche seront reprises dans les classes cochées ci-dessous — pas la date
          prévue/réalisée ni les séances déjà faites, propres à cette classe. Une classe qui a déjà une fiche est
          ignorée, jamais écrasée.
        </p>

        {isLoading ? (
          <Spinner />
        ) : !cibles || cibles.length === 0 ? (
          <EmptyState label="Aucune autre classe n'enseigne cette matière." />
        ) : (
          <div className="flex flex-col divide-y divide-navy-50">
            {cibles.map((cible) => (
              <label
                key={cible.classe_matiere_id}
                className="flex items-start gap-2.5 py-2 first:pt-0 last:pb-0 cursor-pointer"
              >
                <input
                  type="checkbox"
                  checked={selection.has(cible.classe_matiere_id)}
                  onChange={() => basculer(cible.classe_matiere_id)}
                  className="mt-0.5 h-4 w-4 flex-none rounded border-navy-300 text-navy-700 focus:ring-navy-200"
                />
                <span className="min-w-0">
                  <span className="block text-sm font-medium text-navy-700">{cible.classe}</span>
                  <span className="block text-xs text-navy-400">
                    {cible.enseignant ?? '—'}
                    {cible.a_deja_une_fiche && ' · a déjà une fiche (sera ignorée)'}
                  </span>
                </span>
              </label>
            ))}
          </div>
        )}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button onClick={copier} disabled={selection.size === 0 || envoi}>
            {envoi ? 'Copie…' : 'Copier'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
