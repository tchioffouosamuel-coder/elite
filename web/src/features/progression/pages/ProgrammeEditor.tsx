import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus, Trash2, Save, ChevronRight, NotebookPen } from 'lucide-react'
import { fetchTrimestres } from '@/features/pedagogie/api'
import {
  fetchProgramme,
  enregistrerProgramme,
  type ProgressionItem,
  type TypeItem,
} from '@/features/progression/api'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

/** Ce qu'un élément peut contenir : la leçon est toujours terminale. */
const ENFANTS_AUTORISES: Record<TypeItem, TypeItem[]> = {
  module: ['chapitre', 'lecon'],
  chapitre: ['lecon'],
  lecon: [],
}

const LIBELLES: Record<TypeItem, string> = {
  module: 'Module',
  chapitre: 'Chapitre',
  lecon: 'Leçon',
}

/** Applique une transformation au nœud désigné par son chemin d'indices. */
function transformer(
  items: ProgressionItem[],
  chemin: number[],
  action: (liste: ProgressionItem[], index: number) => ProgressionItem[],
): ProgressionItem[] {
  const [index, ...reste] = chemin

  if (reste.length === 0) return action(items, index)

  return items.map((item, i) =>
    i === index ? { ...item, enfants: transformer(item.enfants, reste, action) } : item,
  )
}

export function ProgrammeEditor({ classeMatiereId }: { classeMatiereId: number }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const { data: programme, isLoading, isError, refetch } = useQuery({
    queryKey: ['programme', classeMatiereId],
    queryFn: () => fetchProgramme(classeMatiereId),
  })

  const [items, setItems] = useState<ProgressionItem[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (programme) setItems(programme.items)
  }, [programme])

  // Les séquences de l'année, numérotées en continu : les enseignants
  // raisonnent en « séquence 5 », pas en « 2e séquence du 2e trimestre ».
  const sequences = (trimestres ?? []).flatMap((tr, iTr) =>
    (tr.sequences ?? []).map((s, iSeq) => ({
      id: s.id,
      libelle: `Séquence ${iTr * (trimestres?.[0]?.sequences?.length ?? 2) + iSeq + 1} — ${tr.libelle}`,
    })),
  )

  const ajouter = (chemin: number[] | null, type: TypeItem) => {
    const nouveau: ProgressionItem = { type, titre: '', enfants: [], sequence_id: null }

    setItems((liste) =>
      chemin === null
        ? [...liste, nouveau]
        : transformer(liste, chemin, (l, i) =>
            l.map((item, j) => (j === i ? { ...item, enfants: [...item.enfants, nouveau] } : item)),
          ),
    )
  }

  const modifier = (chemin: number[], champ: Partial<ProgressionItem>) => {
    setItems((liste) =>
      transformer(liste, chemin, (l, i) => l.map((item, j) => (j === i ? { ...item, ...champ } : item))),
    )
  }

  const supprimer = (chemin: number[]) => {
    setItems((liste) => transformer(liste, chemin, (l, i) => l.filter((_, j) => j !== i)))
  }

  const enregistrer = async () => {
    setSubmitting(true)
    setMessage(null)
    setErreur(null)
    try {
      const maj = await enregistrerProgramme(classeMatiereId, items)
      setItems(maj.items)
      setMessage(t('progression.saved', { count: maj.lecons }))
      refetch()
    } catch (err) {
      setErreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  const rendre = (liste: ProgressionItem[], chemin: number[] = []) =>
    liste.map((item, index) => {
      const monChemin = [...chemin, index]
      const estLecon = item.type === 'lecon'

      return (
        <div key={monChemin.join('-')} className={chemin.length > 0 ? 'ml-4 border-l border-navy-100 pl-4' : ''}>
          <div className="flex flex-wrap items-center gap-2 py-1.5">
            <span
              className={`flex-none rounded-lg px-2 py-1 text-[10px] font-bold uppercase tracking-wide ${
                estLecon ? 'bg-gold-50 text-gold-600' : 'bg-navy-50 text-navy-600'
              }`}
            >
              {LIBELLES[item.type]}
            </span>

            <input
              value={item.titre}
              onChange={(e) => modifier(monChemin, { titre: e.target.value })}
              placeholder={`Intitulé du ${LIBELLES[item.type].toLowerCase()}`}
              className="min-w-40 flex-1 rounded-lg border border-navy-200 px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
            />

            {estLecon && (
              <select
                value={item.sequence_id ?? ''}
                onChange={(e) => modifier(monChemin, { sequence_id: e.target.value ? Number(e.target.value) : null })}
                className="rounded-lg border border-navy-200 bg-white px-2 py-1.5 text-xs shadow-soft focus:border-navy-400 focus:outline-none"
              >
                <option value="">{t('progression.sequence_libre')}</option>
                {sequences.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.libelle}
                  </option>
                ))}
              </select>
            )}

            {estLecon && item.traitee && (
              <span className="rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-semibold text-green-600">
                {t('progression.traitee')}
              </span>
            )}

            {estLecon && (
              <button
                onClick={() => item.id && navigate(`/progression/lecons/${item.id}/preparation`)}
                disabled={!item.id}
                title={item.id ? 'Fiche de préparation' : 'Enregistrez le programme pour préparer cette leçon'}
                className={`flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold hover:bg-cream-100 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent ${
                  item.a_preparation ? 'text-gold-600' : 'text-navy-500'
                }`}
              >
                <NotebookPen className="h-3.5 w-3.5" />
                Préparer
              </button>
            )}

            {ENFANTS_AUTORISES[item.type].map((type) => (
              <button
                key={type}
                onClick={() => ajouter(monChemin, type)}
                title={`${t('common.add')} ${LIBELLES[type].toLowerCase()}`}
                className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-navy-500 hover:bg-cream-100 hover:text-navy-800"
              >
                <Plus className="h-3 w-3" />
                {LIBELLES[type]}
              </button>
            ))}

            <button
              onClick={() => supprimer(monChemin)}
              title={t('common.delete')}
              className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
            >
              <Trash2 className="h-3.5 w-3.5" />
            </button>
          </div>

          {item.enfants.length > 0 && rendre(item.enfants, monChemin)}
        </div>
      )
    })

  if (isLoading) return <Spinner />
  if (isError || !programme) return <ErrorState />

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
        <div>
          <p className="font-display text-base font-bold text-navy-800">
            {programme.classe.nom} <ChevronRight className="inline h-4 w-4 text-navy-300" /> {programme.matiere.nom}
          </p>
          <p className="text-sm text-navy-400">
            {t('progression.avancement', { traitees: programme.traitees, lecons: programme.lecons })}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <div className="w-32">
            <div className="h-2 overflow-hidden rounded-full bg-navy-100">
              <div className="h-full rounded-full bg-gold-500" style={{ width: `${programme.taux}%` }} />
            </div>
            <p className="mt-1 text-right text-xs font-semibold text-navy-600">{programme.taux} %</p>
          </div>
          <Button onClick={enregistrer} disabled={submitting}>
            <Save className="h-4 w-4" />
            {t('common.save')}
          </Button>
        </div>
      </div>

      {message && <p className="text-sm text-green-600">{message}</p>}
      {erreur && <p className="text-sm text-red-500">{erreur}</p>}

      <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
        {items.length === 0 ? (
          <p className="py-6 text-center text-sm text-navy-400">{t('progression.vide')}</p>
        ) : (
          rendre(items)
        )}

        <div className="mt-3 flex flex-wrap gap-2 border-t border-navy-100 pt-3">
          {(['module', 'chapitre', 'lecon'] as TypeItem[]).map((type) => (
            <Button key={type} variant="secondary" size="sm" onClick={() => ajouter(null, type)}>
              <Plus className="h-3.5 w-3.5" />
              {LIBELLES[type]}
            </Button>
          ))}
        </div>
      </div>
    </div>
  )
}
