import { Fragment, useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Trash2, Save, ChevronRight, Upload, FileSpreadsheet, FileDown, Settings2, X } from 'lucide-react'
import {
  fetchProgramme,
  enregistrerProgramme,
  enregistrerCartouche,
  enregistrerProgressionColonnes,
  importerProgression,
  ouvrirFicheProgressionPdf,
  type ProgressionItem,
  type ProgressionColonneDef,
  type Programme,
} from '@/features/progression/api'
import { succes, erreur as alerteErreur } from '@/shared/lib/alertes'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

type CleFiche = keyof ProgressionItem

/**
 * Colonnes du gabarit, dans l'ordre de la feuille de l'établissement.
 *
 * `competence` (Competency) n'existe que sur le gabarit primaire/maternelle,
 * `teaching_learning_strategies` (Teaching / Strategy) que sur le secondaire
 * — chaque liste ne porte que celles qui concernent son cycle.
 */
const CHAMPS_PRIMAIRE: { cle: CleFiche; libelle: string; lignes?: number }[] = [
  { cle: 'competence', libelle: 'Competency', lignes: 2 },
  { cle: 'expected_learning_outcomes', libelle: 'Learning Outcomes', lignes: 2 },
  { cle: 'entry_behaviour', libelle: 'Entry Behaviour', lignes: 2 },
  { cle: 'teaching_aids', libelle: 'Teaching Aids', lignes: 2 },
  { cle: 'facilitators_activities', libelle: "Teacher's Activities", lignes: 2 },
  { cle: 'learners_activities', libelle: "Learners' Activities", lignes: 2 },
  { cle: 'assessment', libelle: 'Assessment / Evaluation', lignes: 2 },
  { cle: 'assignment', libelle: 'Assignment', lignes: 2 },
  { cle: 'remarks', libelle: 'Remarks', lignes: 1 },
]

const CHAMPS_SECONDAIRE: { cle: CleFiche; libelle: string; lignes?: number }[] = [
  { cle: 'expected_learning_outcomes', libelle: 'Learning Outcomes', lignes: 2 },
  { cle: 'entry_behaviour', libelle: 'Entry Behaviour', lignes: 2 },
  { cle: 'teaching_aids', libelle: 'Resources / Teaching Aids', lignes: 2 },
  { cle: 'teaching_learning_strategies', libelle: 'Teaching / Strategy', lignes: 2 },
  { cle: 'facilitators_activities', libelle: "Teacher's Activities", lignes: 2 },
  { cle: 'learners_activities', libelle: "Learners' Activities", lignes: 2 },
  { cle: 'assessment', libelle: 'Assessment', lignes: 2 },
  { cle: 'assignment', libelle: 'Assignment', lignes: 2 },
  { cle: 'remarks', libelle: 'Remarks', lignes: 1 },
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
  const [programme, setProgramme] = useState<Programme | null>(null)
  const [chargement, setChargement] = useState(true)
  const [erreurChargement, setErreurChargement] = useState(false)

  const [items, setItems] = useState<ProgressionItem[]>([])
  const [colonnes, setColonnes] = useState<ProgressionColonneDef[]>([])
  const [ouverts, setOuverts] = useState<Set<string>>(new Set())
  const [submitting, setSubmitting] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [importOuvert, setImportOuvert] = useState(false)
  const [colonnesOuvert, setColonnesOuvert] = useState(false)
  const [exportEnCours, setExportEnCours] = useState(false)

  const charger = async () => {
    setChargement(true)
    setErreurChargement(false)
    try {
      const data = await fetchProgramme(classeMatiereId)
      setProgramme(data)
      setItems(data.items)
      setColonnes(data.colonnes)
    } catch {
      setErreurChargement(true)
    } finally {
      setChargement(false)
    }
  }

  useEffect(() => {
    charger()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [classeMatiereId])

  const champs = programme?.cycle === 'secondaire' ? CHAMPS_SECONDAIRE : CHAMPS_PRIMAIRE
  const dureeLabel = programme?.cycle === 'secondaire' ? 'Periods' : 'Duration'

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

  const modifierColonneLibre = (chemin: number[], colonneId: number, valeur: string) => {
    setItems((liste) =>
      transformer(liste, chemin, (l, i) =>
        l.map((item, j) =>
          j === i ? { ...item, colonnes_libres: { ...item.colonnes_libres, [colonneId]: valeur } } : item,
        ),
      ),
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
      setProgramme((actuel) => (actuel ? { ...actuel, lecons: maj.lecons, traitees: maj.traitees, taux: maj.taux } : actuel))
      succes(t('progression.saved', { count: maj.lecons }))
    } catch (err) {
      const message = (err as ApiError).message
      setErreur(message)
      alerteErreur(message)
    } finally {
      setSubmitting(false)
    }
  }

  const exporterPdf = async () => {
    setExportEnCours(true)
    try {
      await ouvrirFicheProgressionPdf(classeMatiereId)
    } catch (err) {
      alerteErreur((err as ApiError).message)
    } finally {
      setExportEnCours(false)
    }
  }

  const rendre = (liste: ProgressionItem[], chemin: number[] = []) =>
    liste.map((item, index) => {
      const monChemin = [...chemin, index]
      const cle = monChemin.join('-')

      // Modules et chapitres ne se créent plus — les gabarits de
      // l'établissement ne les connaissent pas — mais un programme déjà
      // découpé garde ses intitulés : ils coiffent les leçons plutôt que de
      // disparaître.
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
              value={item.topic ?? ''}
              onChange={(e) => modifier(monChemin, { topic: e.target.value, titre: e.target.value })}
              placeholder="Topic"
              className={`min-w-40 flex-1 ${CHAMP_CLASSES}`}
            />

            <input
              value={item.sous_topic ?? ''}
              onChange={(e) => modifier(monChemin, { sous_topic: e.target.value })}
              placeholder="Sub-topic"
              className={`min-w-32 flex-1 ${CHAMP_CLASSES}`}
            />

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
                <label className="flex flex-col gap-1">
                  <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">Week</span>
                  <input
                    value={item.semaine ?? ''}
                    onChange={(e) => modifier(monChemin, { semaine: e.target.value })}
                    placeholder={String(index + 1)}
                    className={`w-20 ${CHAMP_CLASSES}`}
                  />
                </label>

                <label className="flex flex-col gap-1">
                  <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">
                    Date Planned
                  </span>
                  <input
                    type="date"
                    value={item.date_prevue ?? ''}
                    onChange={(e) => modifier(monChemin, { date_prevue: e.target.value || null })}
                    className={`w-40 ${CHAMP_CLASSES}`}
                  />
                </label>

                <label className="flex flex-col gap-1">
                  <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">
                    Date Taught
                  </span>
                  <input
                    type="date"
                    value={item.date_realisee ?? ''}
                    onChange={(e) => modifier(monChemin, { date_realisee: e.target.value || null })}
                    className={`w-40 ${CHAMP_CLASSES}`}
                  />
                </label>

                <label className="flex flex-col gap-1">
                  <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">
                    {dureeLabel}
                  </span>
                  <input
                    value={item.duree ?? ''}
                    onChange={(e) => modifier(monChemin, { duree: e.target.value })}
                    className={`w-28 ${CHAMP_CLASSES}`}
                  />
                </label>
              </div>

              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {champs.map(({ cle: champCle, libelle, lignes }) => (
                  <label key={champCle} className="flex flex-col gap-1">
                    <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">{libelle}</span>
                    <textarea
                      rows={lignes}
                      value={(item[champCle] as string | null) ?? ''}
                      onChange={(e) => modifier(monChemin, { [champCle]: e.target.value })}
                      className={CHAMP_CLASSES}
                    />
                  </label>
                ))}

                {colonnes.map((colonne) => (
                  <label key={colonne.id} className="flex flex-col gap-1">
                    <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">
                      {colonne.libelle}
                    </span>
                    <textarea
                      rows={1}
                      value={item.colonnes_libres?.[colonne.id] ?? ''}
                      onChange={(e) => modifierColonneLibre(monChemin, colonne.id, e.target.value)}
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

  if (chargement) return <Spinner />
  if (erreurChargement || !programme) return <ErrorState />

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
        <div className="flex flex-wrap items-center gap-3">
          <div className="w-32">
            <div className="h-2 overflow-hidden rounded-full bg-navy-100">
              <div className="h-full rounded-full bg-gold-500" style={{ width: `${programme.taux}%` }} />
            </div>
            <p className="mt-1 text-right text-xs font-semibold text-navy-600">{programme.taux} %</p>
          </div>
          <Button variant="secondary" size="sm" onClick={() => setColonnesOuvert(true)}>
            <Settings2 className="h-3.5 w-3.5" />
            {t('progression.colonnes_libres')}
          </Button>
          <Button variant="secondary" onClick={() => setImportOuvert(true)}>
            <Upload className="h-4 w-4" />
            {t('progression.importer_fiche')}
          </Button>
          <Button variant="secondary" onClick={exporterPdf} disabled={exportEnCours}>
            <FileDown className="h-4 w-4" />
            PDF
          </Button>
          <Button onClick={enregistrer} disabled={submitting}>
            <Save className="h-4 w-4" />
            {t('common.save')}
          </Button>
        </div>
      </div>

      {programme.cycle === 'secondaire' && (
        <CartoucheSecondaire
          classeMatiereId={classeMatiereId}
          programme={programme}
          onChange={(maj) => setProgramme((actuel) => (actuel ? { ...actuel, ...maj } : actuel))}
        />
      )}

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
            setProgramme((actuel) =>
              actuel ? { ...actuel, lecons: maj.lecons, traitees: maj.traitees, taux: maj.taux } : actuel,
            )
          }}
        />
      )}

      {colonnesOuvert && (
        <ColonnesLibresModal
          classeMatiereId={classeMatiereId}
          colonnes={colonnes}
          onClose={() => setColonnesOuvert(false)}
          onEnregistre={setColonnes}
        />
      )}
    </div>
  )
}

/** Cartouche du gabarit secondaire : Department (déduit), Specialty et Module/Competency (saisis). */
function CartoucheSecondaire({
  classeMatiereId,
  programme,
  onChange,
}: {
  classeMatiereId: number
  programme: Programme
  onChange: (maj: Pick<Programme, 'departement' | 'specialite' | 'module_competence'>) => void
}) {
  const [specialite, setSpecialite] = useState(programme.specialite ?? '')
  const [moduleCompetence, setModuleCompetence] = useState(programme.module_competence ?? '')
  const [enregistrement, setEnregistrement] = useState(false)

  useEffect(() => {
    setSpecialite(programme.specialite ?? '')
    setModuleCompetence(programme.module_competence ?? '')
  }, [programme.specialite, programme.module_competence])

  const enregistrer = async () => {
    setEnregistrement(true)
    try {
      const maj = await enregistrerCartouche(classeMatiereId, {
        specialite: specialite.trim() || null,
        module_competence: moduleCompetence.trim() || null,
      })
      onChange(maj)
      succes('Cartouche enregistré.')
    } catch (err) {
      alerteErreur((err as ApiError).message)
    } finally {
      setEnregistrement(false)
    }
  }

  return (
    <div className="grid gap-3 rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card sm:grid-cols-3">
      <label className="flex flex-col gap-1">
        <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">Department</span>
        <span className="rounded-lg border border-navy-100 bg-cream-50 px-2.5 py-1.5 text-sm text-navy-600">
          {programme.departement ?? '—'}
        </span>
      </label>

      <label className="flex flex-col gap-1">
        <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">Specialty</span>
        <input value={specialite} onChange={(e) => setSpecialite(e.target.value)} onBlur={enregistrer} className={CHAMP_CLASSES} />
      </label>

      <label className="flex flex-col gap-1">
        <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-500">Module / Competency</span>
        <input
          value={moduleCompetence}
          onChange={(e) => setModuleCompetence(e.target.value)}
          onBlur={enregistrer}
          className={CHAMP_CLASSES}
        />
      </label>

      {enregistrement && <span className="sr-only">Enregistrement…</span>}
    </div>
  )
}

/** Jusqu'à dix colonnes propres à la matière — pour ce que le gabarit ne prévoit pas. */
function ColonnesLibresModal({
  classeMatiereId,
  colonnes,
  onClose,
  onEnregistre,
}: {
  classeMatiereId: number
  colonnes: ProgressionColonneDef[]
  onClose: () => void
  onEnregistre: (colonnes: ProgressionColonneDef[]) => void
}) {
  const { t } = useTranslation()
  const [lignes, setLignes] = useState<{ id?: number; libelle: string }[]>(
    colonnes.length > 0 ? colonnes.map((c) => ({ id: c.id, libelle: c.libelle })) : [{ libelle: '' }],
  )
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)

  const MAX = 10

  const ajouter = () => setLignes((l) => (l.length < MAX ? [...l, { libelle: '' }] : l))
  const retirer = (index: number) => setLignes((l) => l.filter((_, i) => i !== index))
  const renommer = (index: number, libelle: string) =>
    setLignes((l) => l.map((ligne, i) => (i === index ? { ...ligne, libelle } : ligne)))

  const enregistrer = async () => {
    const utiles = lignes.filter((l) => l.libelle.trim() !== '')
    setEnvoi(true)
    setErreur(null)
    try {
      const resultat = await enregistrerProgressionColonnes(classeMatiereId, utiles)
      onEnregistre(resultat)
      onClose()
      succes(t('progression.colonnes_enregistrees'))
    } catch (err) {
      setErreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={t('progression.colonnes_libres')} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <p className="text-xs text-navy-500">{t('progression.colonnes_libres_hint')}</p>

        <div className="flex flex-col gap-2">
          {lignes.map((ligne, index) => (
            <div key={index} className="flex items-center gap-2">
              <input
                value={ligne.libelle}
                onChange={(e) => renommer(index, e.target.value)}
                placeholder={t('progression.colonne_placeholder')}
                maxLength={60}
                className={CHAMP_CLASSES}
              />
              <button
                type="button"
                onClick={() => retirer(index)}
                className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          ))}
        </div>

        {lignes.length < MAX && (
          <Button type="button" variant="secondary" size="sm" onClick={ajouter} className="self-start">
            <Plus className="h-3.5 w-3.5" />
            {t('common.add')}
          </Button>
        )}

        {erreur && <p className="text-sm text-red-500">{erreur}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={enregistrer} disabled={envoi}>
            {t('common.save')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

/**
 * Import du classeur « progression sheet » de l'établissement.
 *
 * L'import complète sans écraser : une leçon déjà saisie ne voit remplir que
 * ses champs restés vides. Le fichier doit correspondre au cycle de
 * l'affectation (maternelle/primaire ou secondaire) : l'API le vérifie et
 * refuse sinon, plutôt que de mélanger silencieusement les deux gabarits.
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
