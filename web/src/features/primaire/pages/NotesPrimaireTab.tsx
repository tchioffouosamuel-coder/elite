import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchClasseMatieres, fetchTrimestres } from '@/features/pedagogie/api'
import {
  fetchGrillePrimaire,
  sauvegarderNotesPrimaire,
  LIBELLES_COMPOSANTES,
  type Composante,
  type NotePrimaireInput,
} from '@/features/primaire/api'
import { Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'

/** Clé d'une cellule de la grille : élève × volet × séquence. */
function cle(eleveId: number, composante: Composante, sequenceId: number): string {
  return `${eleveId}|${composante}|${sequenceId}`
}

/**
 * Saisie des notes du primaire : contrairement au secondaire (une note par
 * séquence), une matière se note ici sur plusieurs volets, chacun évalué à
 * chaque séquence du trimestre. La grille couvre donc tout le trimestre.
 */
export function NotesPrimaireTab({ classeId }: { classeId: number }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const { data: affectations } = useQuery({
    queryKey: ['classe-matieres', classeId],
    queryFn: () => fetchClasseMatieres(classeId),
  })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })

  const [classeMatiereId, setClasseMatiereId] = useState<number | ''>('')
  const [trimestreId, setTrimestreId] = useState<number | ''>('')
  const [valeurs, setValeurs] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState<string | null>(null)

  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]

  useEffect(() => {
    if (!trimestreId && trimestreActif) setTrimestreId(trimestreActif.id)
  }, [trimestreActif, trimestreId])

  const { data: grille, isLoading } = useQuery({
    queryKey: ['grille-primaire', classeMatiereId, trimestreId],
    queryFn: () => fetchGrillePrimaire(Number(classeMatiereId), Number(trimestreId)),
    enabled: !!classeMatiereId && !!trimestreId,
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

  /** Chaque volet reçoit une part égale du barème de la matière. */
  const maxParVolet = useMemo(
    () => (grille ? grille.bareme / Math.max(grille.composantes.length, 1) : 20),
    [grille],
  )

  const handleSave = async () => {
    if (!grille || !classeMatiereId) return

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
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Select
          label={t('matieres.title')}
          value={classeMatiereId}
          onChange={(e) => setClasseMatiereId(e.target.value ? Number(e.target.value) : '')}
        >
          <option value="">—</option>
          {affectations?.map((a) => (
            <option key={a.id} value={a.id}>
              {a.matiere.nom}
            </option>
          ))}
        </Select>
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
      </div>

      {!classeMatiereId || !trimestreId ? (
        <EmptyState label={t('notes.select_prompt')} />
      ) : isLoading || !grille ? (
        <Spinner />
      ) : (
        <>
          <p className="text-sm text-navy-500">
            {t('notes.bareme_hint', {
              bareme: grille.bareme,
              volets: grille.composantes.length,
              max: maxParVolet.toFixed(2).replace(/\.00$/, ''),
            })}
          </p>

          <div className="overflow-x-auto rounded-2xl border border-navy-100/70 bg-white shadow-card">
            <table className="w-full min-w-[720px] border-collapse text-sm">
              <thead className="bg-cream-100/70 text-xs font-semibold uppercase tracking-wide text-navy-500">
                <tr>
                  <th rowSpan={2} className="border-b border-navy-100 px-4 py-2 text-left">
                    {t('eleves.nom')}
                  </th>
                  {grille.composantes.map((composante) => (
                    <th
                      key={composante}
                      colSpan={grille.sequences.length}
                      className="border-b border-l border-navy-100 px-3 py-2 text-center"
                    >
                      {LIBELLES_COMPOSANTES[composante]}
                    </th>
                  ))}
                </tr>
                <tr>
                  {grille.composantes.map((composante) =>
                    grille.sequences.map((sequence, index) => (
                      <th
                        key={`${composante}-${sequence.id}`}
                        className={`border-b border-navy-100 px-2 py-1.5 text-center text-[11px] font-medium ${
                          index === 0 ? 'border-l' : ''
                        }`}
                      >
                        S{index + 1}
                      </th>
                    )),
                  )}
                </tr>
              </thead>
              <tbody>
                {grille.lignes.map((ligne) => (
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
                            max={maxParVolet}
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
                  </tr>
                ))}
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
