import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, ClipboardList } from 'lucide-react'
import { fetchMesCompetences } from '@/features/enseignant/api'
import { NotesPrimaireDetail } from '@/features/primaire/pages/NotesPrimaireTab'
import type { ClasseCompetence, Competence } from '@/features/pedagogie/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { Spinner } from '@/shared/ui/Feedback'

/** Saisie des notes de l'enseignant pour une de ses compétences (primaire/maternelle), page dédiée. */
export function RemplirCompetencesPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { classeCompetenceId } = useParams<{ classeCompetenceId: string }>()
  const classeCompetenceIdNumber = Number(classeCompetenceId)

  const { data: affectations, isLoading } = useQuery({ queryKey: ['enseignant-mes-competences'], queryFn: fetchMesCompetences })
  const affectation = affectations?.find((a) => a.classe_competence_id === classeCompetenceIdNumber)

  if (isLoading || !affectations) return <Spinner />

  // `NotesPrimaireDetail` ne lit que `.competence?.label_fr` et `.enseignant?.nom_complet`
  // du prop `matiere` : un objet minimal suffit, pas besoin de refaire l'appel
  // `/classes/{classeId}/competences` déjà couvert par « mes-affectations ».
  const matiere = affectation
    ? ({
        classe_competence_id: affectation.classe_competence_id,
        competence: { label_fr: affectation.competence } as Competence,
        enseignant: null,
        groupe: 1,
        statut: 'actif',
      } as ClasseCompetence)
    : undefined

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <PageHeader
          titre="Évaluer"
          sousTitre={affectation ? `${affectation.competence} — ${affectation.classe}` : undefined}
          icon={ClipboardList}
        />
        <Button type="button" variant="secondary" onClick={() => navigate('/enseignant/mes-competences')}>
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </Button>
      </div>

      <NotesPrimaireDetail classeId={affectation?.classe_id ?? 0} classeMatiereId={classeCompetenceIdNumber} matiere={matiere} />
    </div>
  )
}
