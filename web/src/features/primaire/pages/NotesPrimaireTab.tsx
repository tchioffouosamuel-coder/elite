import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronRight, Search } from 'lucide-react'
import { fetchCompetencesClasse, fetchTrimestres, type ClasseCompetence } from '@/features/pedagogie/api'
import {
  fetchGrillePrimaire,
  sauvegarderNotesPrimaire,
  LIBELLES_COMPOSANTES,
  type Composante,
  type NotePrimaireInput,
} from '@/features/primaire/api'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/** Clé d'une cellule de la grille : élève × volet × séquence. */
function cle(eleveId: number, composante: Composante, sequenceId: number): string {
  return `${eleveId}|${composante}|${sequenceId}`
}

/**
 * Saisie des notes du primaire : contrairement au secondaire (une note par
 * séquence), une COMPÉTENCE se note ici sur plusieurs volets, chacun évalué à
 * chaque séquence du trimestre. La grille couvre donc tout le trimestre.
 *
 * On saisit la compétence et non ses matières : c'est elle que le bulletin
 * note, et l'enseignant renseigne un bloc au lieu d'une dizaine de lignes qui
 * aboutiraient au même endroit.
 */
export function NotesPrimaireTab({
  classeId,
  initialMatiereId,
  onBack,
}: {
  classeId: number
  /** Compétence déjà choisie ailleurs (ex. depuis « Remplissage des notes ») : saute la liste interne. */
  initialMatiereId?: number | null
  onBack?: () => void
}) {
  const { t } = useTranslation()
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedMatiereId, setSelectedMatiereId] = useState<number | null>(null)

  const { data: affectations } = useQuery({
    queryKey: ['classe-competences', classeId],
    queryFn: () => fetchCompetencesClasse(classeId),
  })

  const filteredMatieres = affectations?.filter((a) =>
    (a.competence?.label_fr ?? '').toLowerCase().includes(searchQuery.toLowerCase())
  )

  const controleExterne = initialMatiereId != null

  if (controleExterne) {
    if (!affectations) return <Spinner />

    const matiere = affectations.find((a) => a.classe_competence_id === initialMatiereId)
    return (
      <div className="flex flex-col gap-4">
        <button
          onClick={onBack}
          className="flex items-center gap-2 text-sm text-navy-600 hover:text-navy-800 font-medium"
        >
          ← {t('common.back')}
        </button>
        <NotesPrimaireDetail classeId={classeId} classeMatiereId={initialMatiereId} matiere={matiere} />
      </div>
    )
  }

  if (selectedMatiereId && affectations) {
    // Afficher la vue détaillée de saisie de notes
    const matiere = affectations.find((a) => a.classe_competence_id === selectedMatiereId)
    return (
      <div className="flex flex-col gap-4">
        <button
          onClick={() => setSelectedMatiereId(null)}
          className="flex items-center gap-2 text-sm text-navy-600 hover:text-navy-800 font-medium"
        >
          ← {t('common.back')}
        </button>
        <NotesPrimaireDetail classeId={classeId} classeMatiereId={selectedMatiereId} matiere={matiere} />
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <Input
        placeholder={t('common.search')}
        value={searchQuery}
        onChange={(e) => setSearchQuery(e.target.value)}
        icon={Search}
      />

      {!filteredMatieres || filteredMatieres.length === 0 ? (
        <EmptyState label={searchQuery ? t('common.no_results') : t('competences.aucune_dans_classe')} />
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>{t('competences.title')}</Th>
              <Th className="text-center">{t('common.actions')}</Th>
            </tr>
          </Thead>
          <tbody>
            {filteredMatieres.map((affectation) => (
              <Tr key={affectation.classe_competence_id}>
                <Td className="font-medium">{affectation.competence?.label_fr ?? '—'}</Td>
                <Td className="text-center">
                  <button
                    onClick={() => setSelectedMatiereId(affectation.classe_competence_id)}
                    className="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-navy-600 hover:bg-navy-50 transition-colors"
                  >
                    {t('notes.saisir')}
                    <ChevronRight className="h-4 w-4" />
                  </button>
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}

interface NotesPrimaireDetailProps {
  classeId: number
  /** Attribution de la compétence à la classe : c'est elle qui porte les notes. */
  classeMatiereId: number
  matiere: ClasseCompetence | undefined
}

function NotesPrimaireDetail({ classeMatiereId, matiere }: NotesPrimaireDetailProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [trimestreId, setTrimestreId] = useState<number | ''>('')
  const [valeurs, setValeurs] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState<string | null>(null)

  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })

  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]

  useEffect(() => {
    if (!trimestreId && trimestreActif) setTrimestreId(trimestreActif.id)
  }, [trimestreActif, trimestreId])

  const { data: grille, isLoading } = useQuery({
    queryKey: ['grille-primaire', classeMatiereId, trimestreId],
    queryFn: () => fetchGrillePrimaire(Number(classeMatiereId), Number(trimestreId)),
    enabled: !!trimestreId,
  })

  useEffect(() => {
    if (!grille) return

    const initial: Record<string, string> = {}
    for (const ligne of grille.lignes) {
      for (const composante of grille.composantes) {
        for (const sequence of grille.sequences) {
          const valeur = ligne.notes[composante]?.[sequence.id]
          initial[cle(ligne.eleve_id, composante, sequence.id)] = valeur !== null && valeur !== undefined ? String(valeur) : ''
        }
      }
    }
    setValeurs(initial)
  }, [grille])

  const handleSave = async () => {
    if (!grille) return

    setSubmitting(true)
    setMessage(null)
    try {
      const notes: NotePrimaireInput[] = []
      for (const ligne of grille.lignes) {
        for (const composante of grille.composantes) {
          for (const sequence of grille.sequences) {
            const brut = valeurs[cle(ligne.eleve_id, composante, sequence.id)] ?? ''
            notes.push({
              eleve_id: ligne.eleve_id,
              sequence_id: sequence.id,
              composante,
              valeur: brut.trim() === '' ? null : Number(brut),
            })
          }
        }
      }

      const resultat = await sauvegarderNotesPrimaire(Number(classeMatiereId), notes)
      setMessage(t('notes.saved', { count: resultat.saved }))
      queryClient.invalidateQueries({ queryKey: ['grille-primaire', classeMatiereId, trimestreId] })
    } catch (err) {
      const apiError = err as ApiError
      const premiereErreurChamp = apiError.errors ? Object.values(apiError.errors)[0]?.[0] : undefined
      erreur(premiereErreurChamp ?? apiError.message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="p-4 bg-cream-50 rounded-lg border border-navy-100">
        <h3 className="text-lg font-semibold text-navy-900">{matiere?.competence?.label_fr ?? '—'}</h3>
        <p className="text-sm text-navy-500">{matiere?.enseignant?.nom_complet ?? '—'}</p>
      </div>

      <Select
        label={t('notes.trimestre')}
        value={trimestreId}
        onChange={(e) => setTrimestreId(e.target.value ? Number(e.target.value) : '')}
      >
        {trimestres?.map((tr) => (
          <option key={tr.id} value={tr.id}>
            {tr.libelle}
          </option>
        ))}
      </Select>

      {isLoading || !grille ? (
        <Spinner />
      ) : (
        <>
          <p className="text-sm text-navy-500">
            {t('notes.bareme_hint', {
              bareme: grille.bareme,
              repartition: grille.composantes
                .map((c) => `${LIBELLES_COMPOSANTES[c]} /${grille.repartition[c]}`)
                .join(' · '),
            })}{' '}
            <span className="font-semibold">· Total volets: {grille.composantes.length}</span>
          </p>

          <div className="overflow-x-auto rounded-2xl border border-navy-100/70 bg-white shadow-card">
            <table className="w-full min-w-[720px] border-collapse text-sm">
              <thead className="bg-cream-100/70 text-xs font-semibold uppercase tracking-wide text-navy-500">
                <tr>
                  <th rowSpan={2} className="border-b border-navy-100 px-4 py-2 text-left">
                    {t('eleves.nom_complet')}
                  </th>
                  {grille.composantes.map((composante) => (
                    <th
                      key={composante}
                      colSpan={grille.sequences.length}
                      className="border-b border-l border-navy-100 px-3 py-2 text-center"
                    >
                      {LIBELLES_COMPOSANTES[composante]}
                      <span className="block text-[10px] font-normal normal-case text-navy-400">
                        / {grille.repartition[composante]}
                      </span>
                    </th>
                  ))}
                  <th
                    colSpan={grille.sequences.length + 1}
                    className="border-b border-l border-navy-100 px-3 py-2 text-center"
                  >
                    Totaux
                  </th>
                </tr>
                <tr>
                  {grille.composantes.map((composante) =>
                    grille.sequences.map((sequence, index) => (
                      <th
                        key={`${composante}-${sequence.id}`}
                        className={`border-b border-navy-100 px-2 py-1.5 text-center text-[11px] font-medium ${index === 0 ? 'border-l' : ''
                          }`}
                      >
                        S{index + 1}
                      </th>
                    )),
                  )}
                  {grille.sequences.map((sequence, index) => (
                    <th
                      key={`total-${sequence.id}`}
                      className={`border-b border-navy-100 px-2 py-1.5 text-center text-[11px] font-medium ${index === 0 ? 'border-l' : ''
                        }`}
                    >
                      Total S{index + 1}
                    </th>
                  ))}
                  <th className="border-b border-l border-navy-100 px-2 py-1.5 text-center text-[11px] font-medium">
                    Total Trimestre
                  </th>
                </tr>
              </thead>
              <tbody>
                {grille.lignes.map((ligne) => {
                  // Calculer les totaux par séquence
                  const totauxParSequence = grille.sequences.map((sequence) => {
                    return grille.composantes.reduce((sum, composante) => {
                      const valeur = valeurs[cle(ligne.eleve_id, composante, sequence.id)] ?? ''
                      return sum + (valeur.trim() === '' ? 0 : Number(valeur) || 0)
                    }, 0)
                  })

                  // Calculer le total trimestre : somme divisée par le nombre de séquences
                  const noteTotalTrimestre =
                    totauxParSequence.reduce((sum, total) => sum + total, 0) / grille.sequences.length

                  return (
                    <tr key={ligne.eleve_id} className="border-t border-navy-50 hover:bg-cream-50/80">
                      <td className="px-4 py-2 font-medium text-navy-800">{ligne.nom_complet}</td>
                      {grille.composantes.map((composante) =>
                        grille.sequences.map((sequence, index) => (
                          <td
                            key={`${composante}-${sequence.id}`}
                            className={`px-1.5 py-1.5 text-center ${index === 0 ? 'border-l border-navy-50' : ''}`}
                          >
                            <input
                              type="number"
                              min={0}
                              max={grille.repartition[composante]}
                              step={0.25}
                              value={valeurs[cle(ligne.eleve_id, composante, sequence.id)] ?? ''}
                              onChange={(e) =>
                                setValeurs((v) => ({
                                  ...v,
                                  [cle(ligne.eleve_id, composante, sequence.id)]: e.target.value,
                                }))
                              }
                              className="w-16 rounded-lg border border-navy-200 px-1.5 py-1 text-center text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
                            />
                          </td>
                        )),
                      )}
                      {totauxParSequence.map((total, index) => (
                        <td
                          key={`total-seq-${index}`}
                          className={`border-l border-navy-50 px-2 py-2 text-center font-semibold text-navy-900 ${index === 0 ? 'border-l-2' : ''
                            }`}
                        >
                          {total.toFixed(2)}
                        </td>
                      ))}
                      <td className="border-l-2 border-navy-100 px-3 py-2 text-center font-bold text-navy-900 bg-cream-100/50">
                        {noteTotalTrimestre.toFixed(2)}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          <div className="flex items-center gap-3">
            <Button onClick={handleSave} disabled={submitting}>
              {t('common.save')}
            </Button>
            {message && <span className="text-sm text-green-600">{message}</span>}
          </div>
        </>
      )}
    </div>
  )
}
