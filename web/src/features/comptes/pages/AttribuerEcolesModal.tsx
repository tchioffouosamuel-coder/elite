import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { School as SchoolIcon } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Spinner } from '@/shared/ui/Feedback'
import { succes, erreur } from '@/shared/lib/alertes'
import { attribuerEcolesCompte, type CompteUtilisateur } from '@/features/comptes/api'
import { fetchSchools } from '@/features/classes/api'
import type { ApiError } from '@/shared/types/api'

/**
 * Écoles supplémentaires d'un compte, en plus de son école principale — pour
 * un compte de direction transverse (« Directrice Primaire et Maternelle »,
 * chauffeur/infirmier/vendeur des deux écoles). Cf. `User::ecolesAccessibles()`
 * côté API : le serveur refuse toute école hors du complexe de l'école
 * principale, ce qui remonte ici comme une erreur classique du formulaire.
 */
export function AttribuerEcolesModal({
  compte,
  onClose,
  onAttribue,
}: {
  compte: CompteUtilisateur
  onClose: () => void
  onAttribue: () => void
}) {
  const [submitting, setSubmitting] = useState(false)
  const [selection, setSelection] = useState<number[]>(compte.ecoles_supplementaires.map((e) => e.id))

  const { data: ecoles, isLoading } = useQuery({
    queryKey: ['schools', 'toutes'],
    queryFn: () => fetchSchools(),
  })

  const basculer = (id: number) => {
    setSelection((valeurs) => (valeurs.includes(id) ? valeurs.filter((v) => v !== id) : [...valeurs, id]))
  }

  const onSubmit = async () => {
    setSubmitting(true)
    try {
      await attribuerEcolesCompte(compte.id, selection)
      succes(`Écoles mises à jour pour ${compte.nom}.`)
      onAttribue()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  const choix = (ecoles ?? []).filter((e) => e.id !== compte.school?.id)

  return (
    <Modal title={`Écoles accessibles — ${compte.nom}`} onClose={onClose}>
      <p className="mb-4 flex items-start gap-2 rounded-xl bg-navy-50 px-3.5 py-2.5 text-sm text-navy-700 ring-1 ring-navy-100">
        <SchoolIcon className="mt-0.5 h-4 w-4 flex-none" />
        <span>
          {compte.school
            ? <>École principale : <strong>{compte.school.name}</strong>. Cochez les écoles supplémentaires auxquelles ce compte doit aussi avoir accès.</>
            : "Ce compte n'a pas d'école principale — cochez les écoles auxquelles il doit avoir accès."}
        </span>
      </p>

      {isLoading ? (
        <Spinner />
      ) : (
        <div className="flex max-h-72 flex-col gap-1 overflow-y-auto">
          {choix.map((ecole) => (
            <label
              key={ecole.id}
              className="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-navy-700 hover:bg-navy-50"
            >
              <input
                type="checkbox"
                checked={selection.includes(ecole.id)}
                onChange={() => basculer(ecole.id)}
                className="h-4 w-4 rounded border-navy-300 text-navy-600 focus:ring-navy-500"
              />
              {ecole.name}
            </label>
          ))}
          {choix.length === 0 && <p className="px-2.5 py-2 text-sm text-navy-400">Aucune autre école disponible.</p>}
        </div>
      )}

      <div className="mt-4 flex justify-end gap-2">
        <Button type="button" variant="secondary" onClick={onClose}>
          Annuler
        </Button>
        <Button type="button" disabled={submitting || isLoading} onClick={onSubmit}>
          <SchoolIcon className="h-4 w-4" />
          {submitting ? 'Enregistrement…' : 'Enregistrer'}
        </Button>
      </div>
    </Modal>
  )
}
