import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, ClipboardList } from 'lucide-react'
import { fetchTrimestres, fetchMesAffectationsActives } from '@/features/pedagogie/api'
import { fetchGrilleNotes, sauvegarderNotes } from '@/features/notes/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState, ErrorState } from '@/shared/ui/Feedback'
import { succes } from '@/shared/lib/alertes'

/** Saisie des notes de l'enseignant pour une de ses affectations, sur la séquence active du trimestre en cours. */
export function RemplirNotesPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { classeMatiereId } = useParams<{ classeMatiereId: string }>()
  const classeMatiereIdNumber = Number(classeMatiereId)

  const [sequenceId, setSequenceId] = useState<number | ''>('')
  const [valeurs, setValeurs] = useState<Record<number, string>>({})
  const [submitting, setSubmitting] = useState(false)

  const { data: affectations } = useQuery({ queryKey: ['enseignant-mes-matieres'], queryFn: fetchMesAffectationsActives })
  const affectation = affectations?.find((a) => a.classe_matiere_id === classeMatiereIdNumber)

  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]
  const sequences = trimestreActif?.sequences ?? []

  useEffect(() => {
    if (!sequenceId && sequences.length > 0) setSequenceId(sequences[0].id)
  }, [sequences, sequenceId])

  const { data: grille, isLoading, isError } = useQuery({
    queryKey: ['grille-notes', classeMatiereIdNumber, sequenceId],
    queryFn: () => fetchGrilleNotes(classeMatiereIdNumber, Number(sequenceId)),
    enabled: !!sequenceId,
  })

  useEffect(() => {
    if (grille) setValeurs(Object.fromEntries(grille.map((g) => [g.eleve_id, g.valeur !== null ? String(g.valeur) : ''])))
  }, [grille])

  const handleSave = async () => {
    if (!sequenceId) return
    setSubmitting(true)
    try {
      const notes = Object.entries(valeurs).map(([eleveId, v]) => ({
        eleve_id: Number(eleveId),
        valeur: v.trim() === '' ? null : Number(v),
      }))
      const result = await sauvegarderNotes(classeMatiereIdNumber, Number(sequenceId), notes)
      succes(t('notes.saved', { count: result.saved }))
      queryClient.invalidateQueries({ queryKey: ['grille-notes', classeMatiereIdNumber, sequenceId] })
      queryClient.invalidateQueries({ queryKey: ['enseignant-mes-matieres'] })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <PageHeader
          titre="Remplir les notes"
          sousTitre={affectation ? `${affectation.matiere} — ${affectation.classe}` : undefined}
          icon={ClipboardList}
        />
        <Button type="button" variant="secondary" onClick={() => navigate('/enseignant/mes-matieres')}>
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </Button>
      </div>

      <Select
        label={t('notes.sequence')}
        value={sequenceId}
        onChange={(e) => setSequenceId(e.target.value ? Number(e.target.value) : '')}
        className="max-w-xs"
      >
        {sequences.map((s) => (
          <option key={s.id} value={s.id}>
            {s.libelle}
          </option>
        ))}
      </Select>

      {isLoading ? (
        <Spinner />
      ) : isError ? (
        <ErrorState />
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
          </div>
        </>
      ) : (
        <EmptyState label="Aucun élève actif dans cette classe" />
      )}
    </div>
  )
}
