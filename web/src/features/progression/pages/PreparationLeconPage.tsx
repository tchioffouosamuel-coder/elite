import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Save } from 'lucide-react'
import {
  enregistrerFichePreparation,
  fetchFichePreparation,
  type EtapeLecon,
  type ModeLecon,
} from '@/features/progression/api'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Input, Textarea } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

const MODES: { value: ModeLecon; label: string }[] = [
  { value: 'digital', label: 'Digital' },
  { value: 'practical', label: 'Practical' },
  { value: 'normal', label: 'Normal' },
]

const ETAPES: { key: 'introduction' | 'presentation' | 'conclusion'; label: string }[] = [
  { key: 'introduction', label: 'Stage: Introduction' },
  { key: 'presentation', label: 'Stage: Presentation' },
  { key: 'conclusion', label: 'Stage: Conclusion' },
]

const ETAPE_VIDE: EtapeLecon = { main_points_of_matter: '', learners_activities: '', facilitators_activities: '' }

function CarteEtape({
  titre,
  valeur,
  onChange,
}: {
  titre: string
  valeur: EtapeLecon
  onChange: (valeur: EtapeLecon) => void
}) {
  return (
    <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
      <p className="mb-3 font-display text-sm font-bold text-navy-800">{titre}</p>
      <div className="flex flex-col gap-3">
        <Textarea
          label="Main Points of Matter"
          value={valeur.main_points_of_matter ?? ''}
          onChange={(e) => onChange({ ...valeur, main_points_of_matter: e.target.value })}
          rows={3}
        />
        <Textarea
          label="Learners' Activities"
          value={valeur.learners_activities ?? ''}
          onChange={(e) => onChange({ ...valeur, learners_activities: e.target.value })}
          rows={3}
        />
        <Textarea
          label="Facilitator's Activities"
          value={valeur.facilitators_activities ?? ''}
          onChange={(e) => onChange({ ...valeur, facilitators_activities: e.target.value })}
          rows={3}
        />
      </div>
    </div>
  )
}

export function PreparationLeconPage() {
  const navigate = useNavigate()
  const { leconId } = useParams<{ leconId: string }>()
  const id = Number(leconId)

  const { data: fiche, isLoading, isError, refetch } = useQuery({
    queryKey: ['fiche-preparation', id],
    queryFn: () => fetchFichePreparation(id),
    enabled: Number.isFinite(id),
  })

  const [topic, setTopic] = useState('')
  const [lesson, setLesson] = useState('')
  const [competence, setCompetence] = useState('')
  const [mode, setMode] = useState<ModeLecon | ''>('')
  const [entryBehaviour, setEntryBehaviour] = useState('')
  const [teachingAids, setTeachingAids] = useState('')
  const [teachingLearningStrategies, setTeachingLearningStrategies] = useState('')
  const [references, setReferences] = useState('')
  const [researchQuestions, setResearchQuestions] = useState('')
  const [introduction, setIntroduction] = useState<EtapeLecon>(ETAPE_VIDE)
  const [presentation, setPresentation] = useState<EtapeLecon>(ETAPE_VIDE)
  const [conclusion, setConclusion] = useState<EtapeLecon>(ETAPE_VIDE)

  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [erreurMsg, setErreurMsg] = useState<string | null>(null)

  useEffect(() => {
    if (!fiche) return
    setTopic(fiche.topic ?? '')
    setLesson(fiche.lesson ?? '')
    setCompetence(fiche.competence ?? '')
    setMode(fiche.mode ?? '')
    setEntryBehaviour(fiche.entry_behaviour ?? '')
    setTeachingAids(fiche.teaching_aids ?? '')
    setTeachingLearningStrategies(fiche.teaching_learning_strategies ?? '')
    setReferences(fiche.references ?? '')
    setResearchQuestions(fiche.research_questions ?? '')
    setIntroduction({ ...ETAPE_VIDE, ...fiche.introduction })
    setPresentation({ ...ETAPE_VIDE, ...fiche.presentation })
    setConclusion({ ...ETAPE_VIDE, ...fiche.conclusion })
  }, [fiche])

  const enregistrer = async () => {
    setSubmitting(true)
    setMessage(null)
    setErreurMsg(null)
    try {
      await enregistrerFichePreparation(id, {
        topic: topic || null,
        lesson: lesson || null,
        competence: competence || null,
        mode: mode || null,
        entry_behaviour: entryBehaviour || null,
        teaching_aids: teachingAids || null,
        teaching_learning_strategies: teachingLearningStrategies || null,
        references: references || null,
        research_questions: researchQuestions || null,
        introduction,
        presentation,
        conclusion,
      })
      setMessage('Fiche de préparation enregistrée.')
      refetch()
    } catch (err) {
      setErreurMsg((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  if (!Number.isFinite(id)) return <ErrorState />
  if (isLoading) return <Spinner />
  if (isError || !fiche) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <button
          onClick={() => navigate(-1)}
          className="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
        >
          <ArrowLeft className="h-4 w-4" />
          Retour au programme
        </button>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <PageHeader
            titre={fiche.titre || 'Leçon'}
            sousTitre={`${fiche.classe.nom} › ${fiche.matiere.nom}`}
          />
          <Button onClick={enregistrer} disabled={submitting}>
            <Save className="h-4 w-4" />
            Enregistrer
          </Button>
        </div>
      </div>

      {message && <p className="text-sm text-green-600">{message}</p>}
      {erreurMsg && <p className="text-sm text-red-500">{erreurMsg}</p>}

      <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input label="Topic" value={topic} onChange={(e) => setTopic(e.target.value)} />
          <Input label="Lesson" value={lesson} onChange={(e) => setLesson(e.target.value)} />
        </div>

        <div className="mt-4">
          <Textarea label="Competence" value={competence} onChange={(e) => setCompetence(e.target.value)} rows={2} />
        </div>

        <div className="mt-4">
          <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-navy-500">
            Digital / Practical / Normal
          </span>
          <div className="flex flex-wrap gap-2">
            {MODES.map((m) => (
              <button
                key={m.value}
                type="button"
                onClick={() => setMode(mode === m.value ? '' : m.value)}
                className={`rounded-xl px-3.5 py-2 text-sm font-semibold transition-colors ${
                  mode === m.value
                    ? 'bg-navy-800 text-cream-50 shadow-card'
                    : 'border border-navy-200 bg-white text-navy-600 hover:border-navy-300'
                }`}
              >
                {m.label}
              </button>
            ))}
          </div>
        </div>

        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Textarea label="Entry Behaviour" value={entryBehaviour} onChange={(e) => setEntryBehaviour(e.target.value)} rows={2} />
          <Textarea label="Teaching Aids" value={teachingAids} onChange={(e) => setTeachingAids(e.target.value)} rows={2} />
          <Textarea
            label="Teaching Learning Strategies"
            value={teachingLearningStrategies}
            onChange={(e) => setTeachingLearningStrategies(e.target.value)}
            rows={2}
          />
          <Textarea label="References" value={references} onChange={(e) => setReferences(e.target.value)} rows={2} />
        </div>

        <div className="mt-4">
          <Textarea
            label="Research Questions"
            value={researchQuestions}
            onChange={(e) => setResearchQuestions(e.target.value)}
            rows={2}
          />
        </div>
      </div>

      <div className="flex flex-col gap-4">
        <p className="font-display text-base font-bold text-navy-800">Stages of the Lesson</p>
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <CarteEtape titre={ETAPES[0].label} valeur={introduction} onChange={setIntroduction} />
          <CarteEtape titre={ETAPES[1].label} valeur={presentation} onChange={setPresentation} />
          <CarteEtape titre={ETAPES[2].label} valeur={conclusion} onChange={setConclusion} />
        </div>
      </div>

      <div className="flex justify-end">
        <Button onClick={enregistrer} disabled={submitting}>
          <Save className="h-4 w-4" />
          Enregistrer
        </Button>
      </div>
    </div>
  )
}
