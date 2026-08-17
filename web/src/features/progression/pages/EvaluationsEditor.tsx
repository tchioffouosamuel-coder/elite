import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardList, Plus, Trash2, Save, Pencil } from 'lucide-react'
import {
  fetchEvaluations,
  fetchProgramme,
  creerEvaluation,
  modifierEvaluation,
  supprimerEvaluation,
  type Evaluation,
  type EvaluationPayload,
  type EvaluationQuestion,
  type ProgressionItem,
  type TypeEvaluation,
} from '@/features/progression/api'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { confirmer, erreur as toastErreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TYPES: Record<TypeEvaluation, string> = {
  interrogation: 'Interrogation',
  devoir: 'Devoir',
  examen: 'Examen',
}

const TONS: Record<TypeEvaluation, 'gold' | 'blue' | 'red'> = {
  interrogation: 'gold',
  devoir: 'blue',
  examen: 'red',
}

/** Récupère toutes les leçons d'un programme, à plat, pour le sélecteur de rattachement. */
function aplatirLecons(items: ProgressionItem[]): { id: number; titre: string }[] {
  return items.flatMap((item) => [
    ...(item.type === 'lecon' && item.id ? [{ id: item.id, titre: item.titre }] : []),
    ...aplatirLecons(item.enfants ?? []),
  ])
}

function EvaluationModal({
  classeMatiereId,
  evaluation,
  lecons,
  onClose,
  onSaved,
}: {
  classeMatiereId: number
  evaluation: Evaluation | null
  lecons: { id: number; titre: string }[]
  onClose: () => void
  onSaved: () => void
}) {
  const [titre, setTitre] = useState(evaluation?.titre ?? '')
  const [type, setType] = useState<TypeEvaluation>(evaluation?.type ?? 'interrogation')
  const [datePrevue, setDatePrevue] = useState(evaluation?.date_prevue ?? '')
  const [bareme, setBareme] = useState(evaluation?.bareme ?? 20)
  const [competences, setCompetences] = useState(evaluation?.competences ?? '')
  const [leconId, setLeconId] = useState<number | ''>(evaluation?.progression_item_id ?? '')
  const [questions, setQuestions] = useState<EvaluationQuestion[]>(
    evaluation?.questions.length ? evaluation.questions : [{ enonce: '', bareme_question: 1 }],
  )
  const [submitting, setSubmitting] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)

  const totalQuestions = questions.reduce((s, q) => s + (Number(q.bareme_question) || 0), 0)

  const ajouterQuestion = () => setQuestions((q) => [...q, { enonce: '', bareme_question: 1 }])
  const modifierQuestion = (index: number, champ: Partial<EvaluationQuestion>) =>
    setQuestions((q) => q.map((item, i) => (i === index ? { ...item, ...champ } : item)))
  const supprimerQuestion = (index: number) => setQuestions((q) => q.filter((_, i) => i !== index))

  const enregistrer = async () => {
    setSubmitting(true)
    setErreur(null)
    try {
      const payload: EvaluationPayload = {
        titre,
        type,
        date_prevue: datePrevue || null,
        bareme,
        competences: competences || null,
        progression_item_id: leconId === '' ? null : leconId,
        questions: questions.filter((q) => q.enonce.trim() !== ''),
      }

      if (evaluation) {
        await modifierEvaluation(evaluation.id, payload)
        succes('Évaluation mise à jour.')
      } else {
        await creerEvaluation(classeMatiereId, payload)
        succes('Évaluation créée.')
      }
      onSaved()
      onClose()
    } catch (e) {
      setErreur((e as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={evaluation ? "Modifier l'évaluation" : 'Nouvelle évaluation'} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <Input label="Titre" value={titre} onChange={(e) => setTitre(e.target.value)} placeholder="Ex. : Interrogation n°3" />

        <div className="grid grid-cols-2 gap-3">
          <Select label="Type" value={type} onChange={(e) => setType(e.target.value as TypeEvaluation)}>
            {Object.entries(TYPES).map(([cle, libelle]) => (
              <option key={cle} value={cle}>
                {libelle}
              </option>
            ))}
          </Select>
          <Input label="Barème total" type="number" min={1} value={bareme} onChange={(e) => setBareme(Number(e.target.value))} />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <Input label="Date prévue" type="date" value={datePrevue ?? ''} onChange={(e) => setDatePrevue(e.target.value)} />
          <Select label="Leçon rattachée" value={leconId} onChange={(e) => setLeconId(e.target.value ? Number(e.target.value) : '')}>
            <option value="">—</option>
            {lecons.map((l) => (
              <option key={l.id} value={l.id}>
                {l.titre}
              </option>
            ))}
          </Select>
        </div>

        <Textarea
          label="Compétences évaluées"
          value={competences ?? ''}
          onChange={(e) => setCompetences(e.target.value)}
          placeholder="Séparées par des virgules"
          rows={2}
        />

        <div>
          <div className="mb-2 flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Questions</span>
            <span className={`text-xs font-semibold ${totalQuestions === bareme ? 'text-green-600' : 'text-gold-600'}`}>
              {totalQuestions} / {bareme} pts
            </span>
          </div>
          <div className="flex flex-col gap-2">
            {questions.map((q, i) => (
              <div key={i} className="flex items-start gap-2">
                <textarea
                  value={q.enonce}
                  onChange={(e) => modifierQuestion(i, { enonce: e.target.value })}
                  placeholder={`Énoncé de la question ${i + 1}`}
                  rows={1}
                  className="min-w-0 flex-1 rounded-lg border border-navy-200 px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
                />
                <input
                  type="number"
                  min={0}
                  value={q.bareme_question}
                  onChange={(e) => modifierQuestion(i, { bareme_question: Number(e.target.value) })}
                  className="w-16 flex-none rounded-lg border border-navy-200 px-2 py-1.5 text-center text-sm shadow-soft focus:border-navy-400 focus:outline-none"
                />
                <button
                  onClick={() => supprimerQuestion(i)}
                  className="flex-none rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            ))}
          </div>
          <Button variant="secondary" size="sm" className="mt-2" onClick={ajouterQuestion}>
            <Plus className="h-3.5 w-3.5" />
            Ajouter une question
          </Button>
        </div>

        {erreur && <p className="text-sm text-red-500">{erreur}</p>}

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button onClick={enregistrer} disabled={submitting || !titre.trim()}>
            <Save className="h-4 w-4" />
            {submitting ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

export function EvaluationsEditor({ classeMatiereId }: { classeMatiereId: number }) {
  const queryClient = useQueryClient()
  const [modaleOuverte, setModaleOuverte] = useState<Evaluation | 'nouvelle' | null>(null)

  const { data: evaluations, isLoading } = useQuery({
    queryKey: ['evaluations', classeMatiereId],
    queryFn: () => fetchEvaluations(classeMatiereId),
  })

  // Réutilise le cache du programme déjà chargé par ProgrammeEditor : même
  // classeMatiereId, même clé de requête, aucun appel réseau supplémentaire.
  const { data: programme } = useQuery({
    queryKey: ['programme', classeMatiereId],
    queryFn: () => fetchProgramme(classeMatiereId),
  })
  const lecons = programme ? aplatirLecons(programme.items) : []

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['evaluations', classeMatiereId] })

  const supprimer = async (evaluation: Evaluation) => {
    const ok = await confirmer({
      titre: `Supprimer « ${evaluation.titre} » ?`,
      message: 'Cette action est irréversible.',
      action: 'Supprimer',
    })
    if (!ok) return

    try {
      await supprimerEvaluation(evaluation.id)
      succes('Évaluation supprimée.')
      rafraichir()
    } catch (e) {
      toastErreur((e as ApiError).message)
    }
  }

  return (
    <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="flex items-center gap-2 font-display text-base font-bold text-navy-800">
          <ClipboardList className="h-4 w-4 text-navy-400" />
          Évaluations
        </h2>
        <Button size="sm" onClick={() => setModaleOuverte('nouvelle')}>
          <Plus className="h-3.5 w-3.5" />
          Nouvelle
        </Button>
      </div>

      {isLoading ? (
        <Spinner />
      ) : !evaluations || evaluations.length === 0 ? (
        <EmptyState label="Aucune évaluation préparée pour cette matière." />
      ) : (
        <div className="flex flex-col divide-y divide-navy-50">
          {evaluations.map((ev) => (
            <div key={ev.id} className="flex flex-wrap items-center gap-3 py-2.5">
              <Badge tone={TONS[ev.type]}>{TYPES[ev.type]}</Badge>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-navy-800">{ev.titre}</p>
                <p className="text-xs text-navy-400">
                  {[
                    ev.date_prevue ? new Date(ev.date_prevue).toLocaleDateString('fr-FR') : null,
                    ev.lecon,
                    `${ev.bareme_questions}/${ev.bareme} pts saisis`,
                  ]
                    .filter(Boolean)
                    .join(' · ')}
                </p>
              </div>
              <button
                onClick={() => setModaleOuverte(ev)}
                className="flex-none rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700"
              >
                <Pencil className="h-3.5 w-3.5" />
              </button>
              <button
                onClick={() => supprimer(ev)}
                className="flex-none rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            </div>
          ))}
        </div>
      )}

      {modaleOuverte && (
        <EvaluationModal
          classeMatiereId={classeMatiereId}
          evaluation={modaleOuverte === 'nouvelle' ? null : modaleOuverte}
          lecons={lecons}
          onClose={() => setModaleOuverte(null)}
          onSaved={rafraichir}
        />
      )}
    </div>
  )
}
