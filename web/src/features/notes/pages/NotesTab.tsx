import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronRight } from 'lucide-react'
import { fetchClasseMatieres, fetchTrimestres } from '@/features/pedagogie/api'
import { fetchGrilleNotes, sauvegarderNotes } from '@/features/notes/api'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'

export function NotesTab({ classeId }: { classeId: number }) {
  const { t } = useTranslation()
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedMatiereId, setSelectedMatiereId] = useState<number | null>(null)

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
          onClick={() => setSelectedMatiereId(null)}
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
        icon="search"
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

function NotesDetail({ classeId, classeMatiereId, matiere }: NotesDetailProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [sequenceId, setSequenceId] = useState<number | ''>('')
  const [valeurs, setValeurs] = useState<Record<number, string>>({})
  const [submitting, setSubmitting] = useState(false)
  const [savedMessage, setSavedMessage] = useState<string | null>(null)

  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })

  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]
  const sequences = trimestreActif?.sequences ?? []

  useEffect(() => {
    if (!sequenceId && sequences.length > 0) setSequenceId(sequences[0].id)
  }, [sequences, sequenceId])

  const { data: grille, isLoading } = useQuery({
    queryKey: ['grille-notes', classeMatiereId, sequenceId],
    queryFn: () => fetchGrilleNotes(classeMatiereId, Number(sequenceId)),
    enabled: !!sequenceId,
  })

  useEffect(() => {
    if (grille) {
      setValeurs(Object.fromEntries(grille.map((g) => [g.eleve_id, g.valeur !== null ? String(g.valeur) : ''])))
    }
  }, [grille])

  const handleSave = async () => {
    if (!sequenceId) return
    setSubmitting(true)
    setSavedMessage(null)
    try {
      const notes = Object.entries(valeurs).map(([eleveId, v]) => ({
        eleve_id: Number(eleveId),
        valeur: v.trim() === '' ? null : Number(v),
      }))
      const result = await sauvegarderNotes(classeMatiereId, Number(sequenceId), notes)
      setSavedMessage(t('notes.saved', { count: result.saved }))
      queryClient.invalidateQueries({ queryKey: ['grille-notes', classeMatiereId, sequenceId] })
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

      <Select
        label={t('notes.sequence')}
        value={sequenceId}
        onChange={(e) => setSequenceId(e.target.value ? Number(e.target.value) : '')}
      >
        {sequences.map((s) => (
          <option key={s.id} value={s.id}>
            {s.libelle}
          </option>
        ))}
      </Select>

      {isLoading ? (
        <Spinner />
      ) : grille && grille.length > 0 ? (
        <>
          <Table>
            <Thead>
              <tr>
                <Th>{t('eleves.nom_complet')}</Th>
                <Th>{t('notes.valeur')}</Th>
              </tr>
            </Thead>
            <tbody>
              {grille.map((row) => (
                <Tr key={row.eleve_id}>
                  <Td className="font-medium">{row.nom_complet}</Td>
                  <Td>
                    <input
                      type="number"
                      min={0}
                      max={20}
                      step={0.25}
                      value={valeurs[row.eleve_id] ?? ''}
                      onChange={(e) => setValeurs((v) => ({ ...v, [row.eleve_id]: e.target.value }))}
                      className="w-24 rounded-lg border border-navy-200 px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
                    />
                  </Td>
                </Tr>
              ))}
            </tbody>
          </Table>

          <div className="flex items-center gap-3">
            <Button onClick={handleSave} disabled={submitting}>
              {t('common.save')}
            </Button>
            {savedMessage && <span className="text-sm text-green-600">{savedMessage}</span>}
          </div>
        </>
      ) : (
        <EmptyState label="Aucun élève actif dans cette classe" />
      )}
    </div>
  )
}
