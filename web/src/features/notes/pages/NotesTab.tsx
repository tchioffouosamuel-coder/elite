import { useState, useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueries, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronRight, Repeat, Search } from 'lucide-react'
import { fetchClasseMatieres, fetchTrimestres } from '@/features/pedagogie/api'
import { fetchGrilleNotes, sauvegarderNotes } from '@/features/notes/api'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { succes, confirmer } from '@/shared/lib/alertes'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { NoteInput, messageErreurNote } from '@/shared/ui/NoteInput'

/** Couleur douce de ligne selon la moyenne du trimestre — mêmes seuils que RemplirNotesPage / la version mobile. */
function couleurLigne(moyenne: number | null): string | undefined {
  if (moyenne === null) return undefined
  if (moyenne < 10) return '#FFEBEE'
  if (moyenne < 15) return '#FFFDE7'
  return '#E8F5E9'
}

export function NotesTab({
  classeId,
  initialMatiereId,
  onBack,
}: {
  classeId: number
  initialMatiereId?: number | null
  onBack?: () => void
}) {
  const { t } = useTranslation()
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedMatiereId, setSelectedMatiereId] = useState<number | null>(initialMatiereId ?? null)

  useEffect(() => {
    if (initialMatiereId !== undefined) {
      setSelectedMatiereId(initialMatiereId)
    }
  }, [initialMatiereId])

  const { data: affectations } = useQuery({
    queryKey: ['classe-matieres', classeId],
    queryFn: () => fetchClasseMatieres(classeId),
  })

  // Filtrer les matières selon la recherche
  const filteredMatieres = affectations?.filter((a) =>
    a.matiere.nom.toLowerCase().includes(searchQuery.toLowerCase()) ||
    a.enseignant?.nom_complet.toLowerCase().includes(searchQuery.toLowerCase())
  )

  if (selectedMatiereId && affectations) {
    // Afficher la vue détaillée de saisie de notes
    const matiere = affectations.find((a) => a.id === selectedMatiereId)
    return (
      <div className="flex flex-col gap-4">
        <button
          onClick={() => {
            setSelectedMatiereId(null)
            onBack?.()
          }}
          className="flex items-center gap-2 text-sm text-navy-600 hover:text-navy-800 font-medium"
        >
          ← {t('common.back')}
        </button>
        <NotesDetail classeId={classeId} classeMatiereId={selectedMatiereId} matiere={matiere} />
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
        <EmptyState label={searchQuery ? t('common.no_results') : 'Aucune matière affectée à cette classe'} />
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>{t('matieres.title')}</Th>
              <Th>{t('personnel.enseignant')}</Th>
              <Th>{t('matieres.coefficient')}</Th>
              <Th className="text-center">{t('common.actions')}</Th>
            </tr>
          </Thead>
          <tbody>
            {filteredMatieres.map((affectation) => (
              <Tr key={affectation.id}>
                <Td className="font-medium">{affectation.matiere.nom}</Td>
                <Td>{affectation.enseignant?.nom_complet ?? '—'}</Td>
                <Td>{affectation.coefficient}</Td>
                <Td className="text-center">
                  <button
                    onClick={() => setSelectedMatiereId(affectation.id)}
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

interface NotesDetailProps {
  classeId: number
  classeMatiereId: number
  matiere: any
}

function NotesDetail({ classeMatiereId, matiere }: NotesDetailProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  // valeurs[eleve_id][sequence_id] = texte saisi (chaîne vide = pas de note)
  const [valeurs, setValeurs] = useState<Record<number, Record<number, string>>>({})
  const [noms, setNoms] = useState<Record<number, string>>({})
  const [reconduireSource, setReconduireSource] = useState<number | ''>('')
  const [reconduireCible, setReconduireCible] = useState<number | ''>('')
  const [submitting, setSubmitting] = useState(false)

  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]
  const sequences = trimestreActif?.sequences ?? []

  const grilles = useQueries({
    queries: sequences.map((s) => ({
      queryKey: ['grille-notes', classeMatiereId, s.id],
      queryFn: () => fetchGrilleNotes(classeMatiereId, s.id),
      enabled: !!classeMatiereId,
    })),
  })

  // Ne préremplit chaque séquence qu'une fois : une invalidation de query ne
  // doit pas écraser une saisie déjà en cours (même garde que RemplirNotesPage).
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
        const result = await sauvegarderNotes(classeMatiereId, sequence.id, notes)
        total += result.saved
        prefillFait.current.delete(sequence.id)
        queryClient.invalidateQueries({ queryKey: ['grille-notes', classeMatiereId, sequence.id] })
      }
      succes(t('notes.saved', { count: total }))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="p-4 bg-cream-50 rounded-lg border border-navy-100">
        <h3 className="text-lg font-semibold text-navy-900">{matiere.matiere.nom}</h3>
        <p className="text-sm text-navy-500">{matiere.enseignant?.nom_complet ?? '—'}</p>
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
