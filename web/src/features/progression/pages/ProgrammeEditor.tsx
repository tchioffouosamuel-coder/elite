import { Fragment, useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Plus, Trash2, Save, ChevronRight, Upload, FileSpreadsheet } from 'lucide-react'
import { fetchTrimestres } from '@/features/pedagogie/api'
import {
  fetchProgramme,
  enregistrerProgramme,
  importerProgression,
  type ProgressionItem,
  type Programme,
} from '@/features/progression/api'
import { succes, erreur as alerteErreur } from '@/shared/lib/alertes'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

type CleFiche = keyof ProgressionItem

/**
 * Les colonnes de la fiche de l'établissement, dans l'ordre de la feuille.
 *
 * L'enseignant ne crée plus ses leçons pour les préparer ensuite dans un autre
 * écran : la ligne de progression EST la fiche. Garder l'ordre de la feuille
 * permet de saisir en la lisant, colonne après colonne.
 */
const CHAMPS: { cle: CleFiche; libelle: string; lignes?: number }[] = [
  { cle: 'expected_learning_outcomes', libelle: 'Expected Learning Outcomes', lignes: 2 },
  { cle: 'competence', libelle: 'Competence', lignes: 2 },
  { cle: 'stages_of_lesson', libelle: 'Stages of the Lesson', lignes: 2 },
  { cle: 'entry_behaviour', libelle: 'Entry Behaviour', lignes: 2 },
  { cle: 'teaching_aids', libelle: 'Teaching Aids', lignes: 2 },
  { cle: 'teaching_learning_strategies', libelle: 'Teaching Learning Strategies', lignes: 2 },
  { cle: 'references', libelle: 'References', lignes: 2 },
  { cle: 'introduction', libelle: 'Stage : Introduction', lignes: 3 },
  { cle: 'presentation', libelle: 'Stage : Presentation', lignes: 3 },
  { cle: 'conclusion', libelle: 'Stage : Conclusion', lignes: 3 },
  { cle: 'main_points', libelle: 'Main Points of Matter', lignes: 3 },
  { cle: 'learners_activities', libelle: "Learners' Activities", lignes: 3 },
  { cle: 'facilitators_activities', libelle: "Facilitator's Activities", lignes: 3 },
]

const MODES: { valeur: string; libelle: string }[] = [
  { valeur: 'digital', libelle: 'Digital' },
  { valeur: 'practical', libelle: 'Practical' },
  { valeur: 'normal', libelle: 'Normal' },
]

const CHAMP_CLASSES =
  'w-full rounded-lg border border-navy-200 px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100'

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
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const { data: programme, isLoading, isError, refetch } = useQuery({
    queryKey: ['programme', classeMatiereId],
    queryFn: () => fetchProgramme(classeMatiereId),
  })

  const [items, setItems] = useState<ProgressionItem[]>([])
  const [avancement, setAvancement] = useState<Pick<Programme, 'lecons' | 'traitees' | 'taux'> | null>(null)
  const [ouverts, setOuverts] = useState<Set<string>>(new Set())
  const [submitting, setSubmitting] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [importOuvert, setImportOuvert] = useState(false)

  useEffect(() => {
    if (!programme) return
    setItems(programme.items)
    setAvancement({ lecons: programme.lecons, traitees: programme.traitees, taux: programme.taux })
  }, [programme])

  // Les séquences de l'année, numérotées en continu : les enseignants
  // raisonnent en « séquence 5 », pas en « 2e séquence du 2e trimestre ».
  const sequences = (trimestres ?? []).flatMap((tr, iTr) =>
    (tr.sequences ?? []).map((s, iSeq) => ({
      id: s.id,
      libelle: `Séquence ${iTr * (trimestres?.[0]?.sequences?.length ?? 2) + iSeq + 1} — ${tr.libelle}`,
    })),
  )

  const basculer = (cle: string) =>
    setOuverts((actuels) => {
      const suivant = new Set(actuels)

      if (suivant.has(cle)) {
        suivant.delete(cle)
      } else {
        suivant.add(cle)
      }

      return suivant
    })

  const ajouterLecon = () => {
    setItems((liste) => [...liste, { type: 'lecon', titre: '', enfants: [], sequence_id: null }])
    // La nouvelle ligne s'ouvre d'office : elle est vide, la replier
    // n'afficherait rien à quoi se raccrocher.
    setOuverts((actuels) => new Set(actuels).add(`${items.length}`))
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
    setErreur(null)
    try {
      const maj = await enregistrerProgramme(classeMatiereId, items)
      setItems(maj.items)
      setAvancement({ lecons: maj.lecons, traitees: maj.traitees, taux: maj.taux })
      succes(t('progression.saved', { count: maj.lecons }))
      refetch()
    } catch (err) {
      const message = (err as ApiError).message
      setErreur(message)
      alerteErreur(message)
    } finally {
      setSubmitting(false)
    }
  }

  const rendre = (liste: ProgressionItem[], chemin: number[] = []) =>
    liste.map((item, index) => {
      const monChemin = [...chemin, index]
      const cle = monChemin.join('-')

      // Modules et chapitres ne se créent plus — la feuille de l'établissement
      // ne les connaît pas — mais un programme déjà découpé garde ses
      // intitulés : ils coiffent les leçons plutôt que de disparaître.
      if (item.type !== 'lecon') {
        return (
          <div key={cle} className="mt-4 first:mt-0">
            <div className="flex items-center gap-2 border-b border-navy-100 pb-1.5">
              <input
                value={item.titre}
                onChange={(e) => modifier(monChemin, { titre: e.target.value })}
                placeholder={t('progression.partie')}
                className="min-w-40 flex-1 rounded-lg border border-transparent px-2 py-1 font-display text-sm font-bold text-navy-800 hover:border-navy-200 focus:border-navy-400 focus:outline-none"
              />
              <button
                onClick={() => supprimer(monChemin)}
                title={t('common.delete')}
                className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            </div>
            {item.enfants.length > 0 && <div className="pl-3">{rendre(item.enfants, monChemin)}</div>}
          </div>
        )
      }

      const ouvert = ouverts.has(cle)

      return (
        <Fragment key={cle}>
          <div
            className={`flex flex-wrap items-center gap-2 border-b border-navy-50 py-2 ${
              ouvert ? 'bg-cream-50/60' : ''
            }`}
          >
            <button
              onClick={() => basculer(cle)}
              title={t(ouvert ? 'progression.replier_fiche' : 'progression.deplier_fiche')}
              className="flex-none rounded-lg p-1 text-navy-400 hover:bg-cream-100 hover:text-navy-700"
            >
              <ChevronRight className={`h-4 w-4 transition-transform ${ouvert ? 'rotate-90' : ''}`} />
            </button>

            <input
              value={item.lesson ?? ''}
              onChange={(e) => modifier(monChemin, { lesson: e.target.value, titre: e.target.value })}
              placeholder="Lesson"
              className={`min-w-40 flex-1 ${CHAMP_CLASSES}`}
            />

            <input
              value={item.topic ?? ''}
              onChange={(e) => modifier(monChemin, { topic: e.target.value })}
              placeholder="Topic"
              className={`min-w-32 flex-1 ${CHAMP_CLASSES}`}
            />

            <select
              value={item.mode ?? ''}
              onChange={(e) =>
                modifier(monChemin, { mode: (e.target.value || null) as ProgressionItem['mode'] })
              }
              className="rounded-lg border border-navy-200 bg-white px-2 py-1.5 text-xs shadow-soft focus:border-navy-400 focus:outline-none"
            >
              <option value="">Mode</option>
              {MODES.map((m) => (
                <option key={m.valeur} value={m.valeur}>
                  {m.libelle}
                </option>
              ))}
            </select>

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

            {item.traitee && (
              <span className="rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-semibold text-green-600">
                {t('progression.traitee')}
              </span>
            )}

            <button
              onClick={() => supprimer(monChemin)}
              title={t('common.delete')}
              className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
            >
              <Trash2 className="h-3.5 w-3.5" />
            </button>
          </div>

          {ouvert && (
            <div className="border-b border-navy-100 bg-cream-50/40 px-3 py-3">
              <div className="mb-3 flex flex-wrap gap-2">
                {(
                  [
                    ['term', 'Term', 'w-28'],
                    ['mois', 'Month', 'w-24'],
                    ['semaine', 'Week', 'w-24'],
                  ] as const
                ).map(([champ, libelle, largeur]) => (
                  <label key={champ} className="flex flex-col gap-1">
                    <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">{libelle}</span>
                    <input
                      value={(item[champ] as string | null) ?? ''}
                      onChange={(e) => modifier(monChemin, { [champ]: e.target.value })}
                      className={`${largeur} ${CHAMP_CLASSES}`}
                    />
                  </label>
                ))}

                <label className="flex flex-col gap-1">
                  <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">Date</span>
                  <input
                    type="date"
                    value={item.date_prevue ?? ''}
                    onChange={(e) => modifier(monChemin, { date_prevue: e.target.value || null })}
                    className={`w-40 ${CHAMP_CLASSES}`}
                  />
                </label>
              </div>

              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {CHAMPS.map(({ cle: champ, libelle, lignes }) => (
                  <label key={champ} className="flex flex-col gap-1">
                    <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">{libelle}</span>
                    <textarea
                      rows={lignes}
                      value={(item[champ] as string | null) ?? ''}
                      onChange={(e) => modifier(monChemin, { [champ]: e.target.value })}
                      className={CHAMP_CLASSES}
                    />
                  </label>
                ))}
              </div>
            </div>
          )}
        </Fragment>
      )
    })

  if (isLoading) return <Spinner />
  if (isError || !programme) return <ErrorState />

  const compteur = avancement ?? programme

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
        <div>
          <p className="font-display text-base font-bold text-navy-800">
            {programme.classe.nom} <ChevronRight className="inline h-4 w-4 text-navy-300" /> {programme.matiere.nom}
          </p>
          <p className="text-sm text-navy-400">
            {t('progression.avancement', { traitees: compteur.traitees, lecons: compteur.lecons })}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <div className="w-32">
            <div className="h-2 overflow-hidden rounded-full bg-navy-100">
              <div className="h-full rounded-full bg-gold-500" style={{ width: `${compteur.taux}%` }} />
            </div>
            <p className="mt-1 text-right text-xs font-semibold text-navy-600">{compteur.taux} %</p>
          </div>
          <Button variant="secondary" onClick={() => setImportOuvert(true)}>
            <Upload className="h-4 w-4" />
            {t('progression.importer_fiche')}
          </Button>
          <Button onClick={enregistrer} disabled={submitting}>
            <Save className="h-4 w-4" />
            {t('common.save')}
          </Button>
        </div>
      </div>

      {erreur && <p className="text-sm text-red-500">{erreur}</p>}

      <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
        {items.length === 0 ? (
          <p className="py-6 text-center text-sm text-navy-400">{t('progression.vide')}</p>
        ) : (
          rendre(items)
        )}

        <div className="mt-3 flex flex-wrap gap-2 border-t border-navy-100 pt-3">
          <Button variant="secondary" size="sm" onClick={ajouterLecon}>
            <Plus className="h-3.5 w-3.5" />
            {t('progression.lecon')}
          </Button>
        </div>
      </div>

      {importOuvert && (
        <ImportFicheModal
          classeMatiereId={classeMatiereId}
          onClose={() => setImportOuvert(false)}
          onImporte={(maj) => {
            setItems(maj.items)
            setAvancement({ lecons: maj.lecons, traitees: maj.traitees, taux: maj.taux })
            refetch()
          }}
        />
      )}
    </div>
  )
}

/**
 * Import du classeur « progression sheet » de l'établissement.
 *
 * L'import complète sans écraser : une leçon déjà saisie ne voit remplir que
 * ses champs restés vides. C'est dit avant l'envoi, pas après — c'est là que
 * l'information sert.
 */
function ImportFicheModal({
  classeMatiereId,
  onClose,
  onImporte,
}: {
  classeMatiereId: number
  onClose: () => void
  onImporte: (programme: Programme) => void
}) {
  const { t } = useTranslation()
  const [fichier, setFichier] = useState<File | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)

  const envoyer = async () => {
    if (!fichier) return
    setEnvoi(true)
    setErreur(null)
    try {
      const resultat = await importerProgression(classeMatiereId, fichier)
      onImporte(resultat)
      onClose()
      succes(
        t('progression.import_resultat', { creees: resultat.creees, completees: resultat.completees }) +
          (resultat.ignorees > 0 ? ` ${t('progression.import_ignorees', { count: resultat.ignorees })}` : ''),
      )
    } catch (err) {
      setErreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={t('progression.import_titre')} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <div className="flex gap-2 rounded-lg bg-cream-100 p-3 text-xs text-navy-600">
          <FileSpreadsheet className="h-4 w-4 flex-none text-navy-400" />
          <div className="flex flex-col gap-1">
            <p>{t('progression.import_format')}</p>
            <p className="text-navy-500">{t('progression.import_complete')}</p>
          </div>
        </div>

        <label className="flex flex-col gap-1.5">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('import.file')}</span>
          <input
            type="file"
            accept=".xlsx,.xls"
            onChange={(e) => setFichier(e.target.files?.[0] ?? null)}
            className="w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm shadow-soft file:mr-3 file:rounded-lg file:border-0 file:bg-navy-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-cream-50"
          />
        </label>

        {erreur && <p className="text-sm text-red-500">{erreur}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={envoyer} disabled={!fichier || envoi}>
            <Upload className="h-4 w-4" />
            {t('import.submit')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
