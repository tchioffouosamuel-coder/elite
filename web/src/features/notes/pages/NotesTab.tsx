import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Upload } from 'lucide-react'
import { fetchClasseMatieres, fetchTrimestres } from '@/features/pedagogie/api'
import { fetchGrilleNotes, sauvegarderNotes } from '@/features/notes/api'
import { Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { ImportModal } from '@/shared/ui/ImportModal'

export function NotesTab({ classeId }: { classeId: number }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [showImport, setShowImport] = useState(false)

  const { data: affectations } = useQuery({ queryKey: ['classe-matieres', classeId], queryFn: () => fetchClasseMatieres(classeId) })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })

  const [classeMatiereId, setClasseMatiereId] = useState<number | ''>('')
  const [sequenceId, setSequenceId] = useState<number | ''>('')
  const [valeurs, setValeurs] = useState<Record<number, string>>({})
  const [submitting, setSubmitting] = useState(false)
  const [savedMessage, setSavedMessage] = useState<string | null>(null)

  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]
  const sequences = trimestreActif?.sequences ?? []

  useEffect(() => {
    if (!sequenceId && sequences.length > 0) setSequenceId(sequences[0].id)
  }, [sequences, sequenceId])

  const { data: grille, isLoading } = useQuery({
    queryKey: ['grille-notes', classeMatiereId, sequenceId],
    queryFn: () => fetchGrilleNotes(Number(classeMatiereId), Number(sequenceId)),
    enabled: !!classeMatiereId && !!sequenceId,
  })

  useEffect(() => {
    if (grille) {
      setValeurs(Object.fromEntries(grille.map((g) => [g.eleve_id, g.valeur !== null ? String(g.valeur) : ''])))
    }
  }, [grille])

  const handleSave = async () => {
    if (!classeMatiereId || !sequenceId) return
    setSubmitting(true)
    setSavedMessage(null)
    try {
      const notes = Object.entries(valeurs).map(([eleveId, v]) => ({
        eleve_id: Number(eleveId),
        valeur: v.trim() === '' ? null : Number(v),
      }))
      const result = await sauvegarderNotes(Number(classeMatiereId), Number(sequenceId), notes)
      setSavedMessage(t('notes.saved', { count: result.saved }))
      queryClient.invalidateQueries({ queryKey: ['grille-notes', classeMatiereId, sequenceId] })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Select label={t('matieres.title')} value={classeMatiereId} onChange={(e) => setClasseMatiereId(e.target.value ? Number(e.target.value) : '')}>
          <option value="">—</option>
          {affectations?.map((a) => (
            <option key={a.id} value={a.id}>
              {a.matiere.nom}
            </option>
          ))}
        </Select>
        <Select label={t('notes.sequence')} value={sequenceId} onChange={(e) => setSequenceId(e.target.value ? Number(e.target.value) : '')}>
          {sequences.map((s) => (
            <option key={s.id} value={s.id}>
              {s.libelle}
            </option>
          ))}
        </Select>
      </div>

      {!classeMatiereId || !sequenceId ? (
        <EmptyState label={t('notes.select_prompt')} />
      ) : isLoading ? (
        <Spinner />
      ) : (
        <>
          <div className="flex justify-end">
            <Button variant="secondary" size="sm" onClick={() => setShowImport(true)}>
              <Upload className="h-3.5 w-3.5" />
              {t('import.title')}
            </Button>
          </div>
          <Table>
            <Thead>
              <tr>
                <Th>{t('eleves.nom')}</Th>
                <Th>{t('notes.valeur')}</Th>
              </tr>
            </Thead>
            <tbody>
              {grille?.map((row) => (
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

          {showImport && (
            <ImportModal
              title={t('import.title')}
              url={`/classe-matieres/${classeMatiereId}/notes/import`}
              columns={['matricule', 'note']}
              extraFields={{ sequence_id: Number(sequenceId) }}
              onClose={() => setShowImport(false)}
              onImported={() => queryClient.invalidateQueries({ queryKey: ['grille-notes', classeMatiereId, sequenceId] })}
            />
          )}
        </>
      )}
    </div>
  )
}
