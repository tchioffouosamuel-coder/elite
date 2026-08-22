import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Target } from 'lucide-react'
import {
  attribuerCompetences,
  fetchCompetences,
  fetchCompetencesClasse,
} from '@/features/pedagogie/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Attribution de compétences à une classe du primaire ou de la maternelle.
 *
 * On coche des blocs, pas des matières : chaque compétence retenue installe
 * d'office ses matières dans la classe, avec l'enseignant désigné. C'est le
 * geste qui remplace la saisie matière par matière.
 *
 * Les compétences déjà attribuées restent cochables — réattribuer complète les
 * matières manquantes sans rien dupliquer, ce qui est le moyen de rattraper une
 * matière ajoutée au référentiel après coup.
 */
export function AttribuerCompetencesModal({
  classeId,
  classeNom,
  titulaireId,
  onClose,
  onAttribuees,
}: {
  classeId: number
  classeNom?: string
  titulaireId?: number | null
  onClose: () => void
  onAttribuees: () => void
}) {
  const { t } = useTranslation()
  const [choisies, setChoisies] = useState<Set<number>>(new Set())
  const [personnelId, setPersonnelId] = useState<number | ''>(titulaireId ?? '')
  const [envoi, setEnvoi] = useState(false)

  const { data: competences, isLoading } = useQuery({ queryKey: ['competences'], queryFn: fetchCompetences })
  const { data: dejaAttribuees } = useQuery({
    queryKey: ['classe-competences', classeId],
    queryFn: () => fetchCompetencesClasse(classeId),
  })
  const { data: personnels } = useQuery({
    queryKey: ['personnels', 'attribution-competences'],
    queryFn: () => fetchPersonnels({ per_page: 500 }),
  })

  const idsDejaAttribuees = new Set(
    (dejaAttribuees ?? []).map((attribution) => attribution.competence?.id).filter(Boolean) as number[],
  )

  const basculer = (id: number) =>
    setChoisies((courant) => {
      const suivant = new Set(courant)

      if (suivant.has(id)) {
        suivant.delete(id)
      } else {
        suivant.add(id)
      }

      return suivant
    })

  const valider = async () => {
    if (choisies.size === 0) return

    setEnvoi(true)
    try {
      const resultat = await attribuerCompetences(classeId, [...choisies], personnelId === '' ? null : Number(personnelId))
      succes(t('competences.attribuees', { competences: resultat.attribuees, matieres: resultat.matieres }))
      onAttribuees()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={t('competences.attribuer_titre', { classe: classeNom ?? '' })} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <p className="rounded-xl border border-navy-100 bg-cream-50 px-3.5 py-2.5 text-xs text-navy-500">
          {t('competences.attribuer_aide')}
        </p>

        <Select
          label={t('competences.enseignant')}
          value={personnelId}
          onChange={(e) => setPersonnelId(e.target.value ? Number(e.target.value) : '')}
        >
          <option value="">—</option>
          {personnels?.map((personnel) => (
            <option key={personnel.id} value={personnel.id}>
              {personnel.nom_complet}
            </option>
          ))}
        </Select>

        {isLoading ? (
          <Spinner />
        ) : (competences ?? []).length === 0 ? (
          <p className="rounded-xl border border-navy-100 bg-cream-50 px-3.5 py-2.5 text-sm text-navy-400">
            {t('competences.aucune_pour_ecole')}
          </p>
        ) : (
          <div className="flex max-h-72 flex-col divide-y divide-navy-50 overflow-y-auto rounded-xl border border-navy-100">
            {competences?.map((competence) => (
              <label
                key={competence.id}
                className="flex cursor-pointer items-start gap-2.5 px-3 py-2.5 text-sm hover:bg-cream-50"
              >
                <input
                  type="checkbox"
                  checked={choisies.has(competence.id)}
                  onChange={() => basculer(competence.id)}
                  className="mt-0.5 h-4 w-4 flex-none rounded border-navy-300 text-gold-600 focus:ring-gold-500"
                />
                <span className="flex min-w-0 flex-col">
                  <span className="flex flex-wrap items-center gap-2">
                    <span className="font-medium text-navy-800">{competence.label_fr}</span>
                    <span className="text-xs text-navy-400">
                      {t('competences.sur_bareme', { bareme: competence.notation })}
                    </span>
                    {idsDejaAttribuees.has(competence.id) && (
                      <span className="text-xs font-semibold text-green-600">✓</span>
                    )}
                  </span>
                  <span className="truncate text-xs text-navy-400">
                    {(competence.matieres?.length ?? 0) === 0
                      ? t('competences.sans_matiere')
                      : competence.matieres?.map((matiere) => matiere.nom).join(' · ')}
                  </span>
                </span>
              </label>
            ))}
          </div>
        )}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={valider} disabled={envoi || choisies.size === 0}>
            <Target className="h-4 w-4" />
            {envoi ? t('common.loading') : t('competences.attribuer')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
