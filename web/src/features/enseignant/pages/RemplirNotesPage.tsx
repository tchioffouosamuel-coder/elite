import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQueries, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, ClipboardList, Repeat } from 'lucide-react'
import { fetchTrimestres, fetchMesAffectationsActives } from '@/features/pedagogie/api'
import { fetchGrilleNotes, sauvegarderNotes } from '@/features/notes/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState, ErrorState } from '@/shared/ui/Feedback'
import { succes, confirmer } from '@/shared/lib/alertes'
import { NoteInput, messageErreurNote } from '@/shared/ui/NoteInput'

/** Couleur douce de ligne selon la moyenne du trimestre — mêmes seuils que la version mobile. */
function couleurLigne(moyenne: number | null): string | undefined {
  if (moyenne === null) return undefined
  if (moyenne < 10) return '#FFEBEE'
  if (moyenne < 15) return '#FFFDE7'
  return '#E8F5E9'
}

/** Saisie des notes de l'enseignant pour une de ses affectations, sur toutes les séquences du trimestre en cours à la fois. */
export function RemplirNotesPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { classeMatiereId } = useParams<{ classeMatiereId: string }>()
  const classeMatiereIdNumber = Number(classeMatiereId)

  // valeurs[eleve_id][sequence_id] = texte saisi (chaîne vide = pas de note)
  const [valeurs, setValeurs] = useState<Record<number, Record<number, string>>>({})
  const [noms, setNoms] = useState<Record<number, string>>({})
  const [reconduireSource, setReconduireSource] = useState<number | ''>('')
  const [reconduireCible, setReconduireCible] = useState<number | ''>('')
  const [submitting, setSubmitting] = useState(false)

  const { data: affectations } = useQuery({ queryKey: ['enseignant-mes-matieres'], queryFn: fetchMesAffectationsActives })
  const affectation = affectations?.find((a) => a.classe_matiere_id === classeMatiereIdNumber)

  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]
  const sequences = trimestreActif?.sequences ?? []

  const grilles = useQueries({
    queries: sequences.map((s) => ({
      queryKey: ['grille-notes', classeMatiereIdNumber, s.id],
      queryFn: () => fetchGrilleNotes(classeMatiereIdNumber, s.id),
      enabled: !!classeMatiereIdNumber,
    })),
  })

  // Ne préremplit chaque séquence qu'une fois : une invalidation de query ne
  // doit pas écraser une saisie déjà en cours (même garde que `_prefillDone`
  // côté Flutter, cf. saisir_notes_classe_screen.dart).
  const prefillFait = useRef<Set<number>>(new Set())
  const grillesSignature = grilles.map((g) => g.dataUpdatedAt).join(',')

  useEffect(() => {
    grilles.forEach((requete, index) => {
      const sequenceId = sequences[index]?.id
      if (!sequenceId || !requete.data || prefillFait.current.has(sequenceId)) return
      prefillFait.current.add(sequenceId)

      setValeurs((precedent) => {
        const suivant = { ...precedent }
        for (const ligne of requete.data!) {
          suivant[ligne.eleve_id] = { ...(suivant[ligne.eleve_id] ?? {}), [sequenceId]: ligne.valeur !== null ? String(ligne.valeur) : '' }
        }
        return suivant
      })
      setNoms((precedent) => ({ ...precedent, ...Object.fromEntries(requete.data!.map((l) => [l.eleve_id, l.nom_complet])) }))
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [grillesSignature, sequences.map((s) => s.id).join(',')])

  const lignes = Object.entries(noms)
    .map(([id, nom_complet]) => ({ eleve_id: Number(id), nom_complet }))
    .sort((a, b) => a.nom_complet.localeCompare(b.nom_complet))

  const moyenneLigne = (eleveId: number): number | null => {
    const valeursNumeriques = sequences
      .map((s) => valeurs[eleveId]?.[s.id])
      .filter((v): v is string => v !== undefined && v.trim() !== '')
      .map(Number)
      .filter((n) => !Number.isNaN(n))

    return valeursNumeriques.length > 0 ? valeursNumeriques.reduce((a, b) => a + b, 0) / valeursNumeriques.length : null
  }

  const notesInvalides = Object.values(valeurs).some((parSequence) =>
    Object.values(parSequence).some((v) => messageErreurNote(v, 20) !== undefined),
  )

  const isLoading = grilles.some((g) => g.isLoading)
  const isError = grilles.some((g) => g.isError)

  const reconduire = async () => {
    if (!reconduireSource || !reconduireCible || reconduireSource === reconduireCible) return

    const cibleDejaRemplie = Object.values(valeurs).some((parSequence) => (parSequence[reconduireCible] ?? '').trim() !== '')
    if (cibleDejaRemplie) {
      const confirme = await confirmer({
        titre: t('notes.reconduire_confirm_titre'),
        message: t('notes.reconduire_confirm_message'),
        action: t('notes.reconduire'),
      })
      if (!confirme) return
    }

    setValeurs((precedent) => {
      const suivant: Record<number, Record<number, string>> = {}
      for (const [eleveId, parSequence] of Object.entries(precedent)) {
        suivant[Number(eleveId)] = { ...parSequence, [reconduireCible]: parSequence[reconduireSource] ?? '' }
      }
      return suivant
    })
    succes(t('notes.reconduire_ok'))
  }

  const handleSave = async () => {
    if (sequences.length === 0 || notesInvalides) return
    setSubmitting(true)
    try {
      let total = 0
      for (const sequence of sequences) {
        const notes = lignes.map((l) => {
          const v = valeurs[l.eleve_id]?.[sequence.id] ?? ''
          return { eleve_id: l.eleve_id, valeur: v.trim() === '' ? null : Number(v) }
        })
        const result = await sauvegarderNotes(classeMatiereIdNumber, sequence.id, notes)
        total += result.saved
        prefillFait.current.delete(sequence.id)
        queryClient.invalidateQueries({ queryKey: ['grille-notes', classeMatiereIdNumber, sequence.id] })
      }
      succes(t('notes.saved', { count: total }))
      queryClient.invalidateQueries({ queryKey: ['enseignant-mes-matieres'] })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <PageHeader
          titre={t('notes.saisir')}
          sousTitre={affectation ? `${affectation.matiere} — ${affectation.classe}${trimestreActif ? ` — ${trimestreActif.libelle}` : ''}` : undefined}
          icon={ClipboardList}
        />
        <Button type="button" variant="secondary" onClick={() => navigate('/enseignant/mes-matieres')}>
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </Button>
      </div>

      {sequences.length > 1 && (
        <div className="flex flex-wrap items-end gap-3 rounded-xl border border-navy-100 bg-white/75 p-3 shadow-soft">
          <Select
            label={t('notes.reconduire_de')}
            value={reconduireSource}
            onChange={(e) => setReconduireSource(e.target.value ? Number(e.target.value) : '')}
            className="max-w-40"
          >
            <option value="">—</option>
            {sequences.map((s) => (
              <option key={s.id} value={s.id}>
                {s.libelle}
              </option>
            ))}
          </Select>
          <Select
            label={t('notes.reconduire_vers')}
            value={reconduireCible}
            onChange={(e) => setReconduireCible(e.target.value ? Number(e.target.value) : '')}
            className="max-w-40"
          >
            <option value="">—</option>
            {sequences.map((s) => (
              <option key={s.id} value={s.id}>
                {s.libelle}
              </option>
            ))}
          </Select>
          <Button
            type="button"
            variant="secondary"
            onClick={reconduire}
            disabled={!reconduireSource || !reconduireCible || reconduireSource === reconduireCible}
          >
            <Repeat className="h-4 w-4" />
            {t('notes.reconduire')}
          </Button>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : isError ? (
        <ErrorState />
      ) : lignes.length > 0 ? (
        <>
          <Table>
            <Thead>
              <tr>
                <Th className="text-right">{t('eleves.nom_complet')}</Th>
                {sequences.map((s) => (
                  <Th key={s.id} className="text-center">
                    {s.libelle}
                  </Th>
                ))}
                <Th className="text-center">{t('notes.trim_colonne')}</Th>
              </tr>
            </Thead>
            <tbody>
              {lignes.map((ligne) => {
                const moyenne = moyenneLigne(ligne.eleve_id)
                return (
                  <Tr key={ligne.eleve_id} style={{ backgroundColor: couleurLigne(moyenne) }}>
                    <Td className="text-right font-medium">{ligne.nom_complet}</Td>
                    {sequences.map((s) => (
                      <Td key={s.id} className="text-center">
                        <NoteInput
                          max={20}
                          value={valeurs[ligne.eleve_id]?.[s.id] ?? ''}
                          onChange={(v) => setValeurs((prev) => ({ ...prev, [ligne.eleve_id]: { ...prev[ligne.eleve_id], [s.id]: v } }))}
                          className="w-24"
                        />
                      </Td>
                    ))}
                    <Td className="text-center font-semibold">{moyenne !== null ? moyenne.toFixed(2) : '—'}</Td>
                  </Tr>
                )
              })}
            </tbody>
          </Table>

          <div className="flex items-center gap-3">
            <Button onClick={handleSave} disabled={submitting || notesInvalides}>
              {t('common.save')}
            </Button>
            {notesInvalides && <span className="text-sm font-medium text-red-500">{t('notes.invalid_hint')}</span>}
          </div>
        </>
      ) : (
        <EmptyState label="Aucun élève actif dans cette classe" />
      )}
    </div>
  )
}
