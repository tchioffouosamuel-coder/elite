import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { FileSpreadsheet } from 'lucide-react'
import { fetchPersonnels } from '@/features/personnel/api'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { fetchAffectationsMatieresDe, fetchAffectationsCompetencesDe } from '@/features/resultats/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'

/**
 * Point 17 de la punch-list : import/export des résultats par enseignant —
 * la direction choisit un agent et retrouve, en un seul écran, le modèle
 * Excel / l'export / l'import de chacune de ses affectations (matières au
 * secondaire, compétences au primaire/maternelle), sans naviguer classe par
 * classe. Réservé à `pedagogie.suivi_saisies`, même privilège que le tableau
 * de suivi des saisies (cf. route côté API).
 */
export function ImportExportEnseignantPage() {
  const { t } = useTranslation()
  const [personnelId, setPersonnelId] = useState<number | ''>('')
  const [trimestreId, setTrimestreId] = useState<number | ''>('')
  const [sequenceId, setSequenceId] = useState<number | ''>('')

  const { data: personnels, isLoading: chargementPersonnels } = useQuery({
    queryKey: ['personnels', 'import-export'],
    queryFn: () => fetchPersonnels({ per_page: 500, statut: 'actif' }),
  })

  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]
  const trimestre = trimestres?.find((tr) => tr.id === (trimestreId || trimestreActif?.id))
  const sequence = trimestre?.sequences.find((s) => s.id === (sequenceId || trimestre.sequences[0]?.id))

  const { data: matieres, isLoading: chargementMatieres } = useQuery({
    queryKey: ['affectations-matieres-de', personnelId],
    queryFn: () => fetchAffectationsMatieresDe(Number(personnelId)),
    enabled: !!personnelId,
  })

  const { data: competences, isLoading: chargementCompetences } = useQuery({
    queryKey: ['affectations-competences-de', personnelId],
    queryFn: () => fetchAffectationsCompetencesDe(Number(personnelId)),
    enabled: !!personnelId,
  })

  const chargement = chargementMatieres || chargementCompetences

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('import_export_enseignant.title')}
        sousTitre={t('import_export_enseignant.hint')}
        icon={FileSpreadsheet}
      />

      <Card>
        <div className="flex flex-wrap gap-3">
          <Select
            label={t('import_export_enseignant.enseignant')}
            value={personnelId}
            onChange={(e) => setPersonnelId(e.target.value ? Number(e.target.value) : '')}
            disabled={chargementPersonnels}
          >
            <option value="">{t('import_export_enseignant.choisir')}</option>
            {personnels?.map((p) => (
              <option key={p.id} value={p.id}>
                {p.nom_complet}
                {p.fonction ? ` — ${p.fonction}` : ''}
              </option>
            ))}
          </Select>

          <Select
            label={t('notes.trimestre')}
            value={trimestre?.id ?? ''}
            onChange={(e) => {
              setTrimestreId(e.target.value ? Number(e.target.value) : '')
              setSequenceId('')
            }}
          >
            {trimestres?.map((tr) => (
              <option key={tr.id} value={tr.id}>
                {tr.libelle}
              </option>
            ))}
          </Select>

          {trimestre && trimestre.sequences.length > 0 && (
            <Select
              label={t('notes.sequence')}
              value={sequence?.id ?? ''}
              onChange={(e) => setSequenceId(e.target.value ? Number(e.target.value) : '')}
            >
              {trimestre.sequences.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.libelle}
                </option>
              ))}
            </Select>
          )}
        </div>
      </Card>

      {!personnelId ? (
        <EmptyState label={t('import_export_enseignant.choisir_hint')} />
      ) : chargement ? (
        <Spinner />
      ) : (
        <>
          {matieres && matieres.length > 0 && (
            <Card>
              <h2 className="mb-3 font-display text-sm font-bold tracking-tight text-navy-900">
                {t('import_export_enseignant.matieres')}
              </h2>
              <div className="flex flex-col divide-y divide-navy-50">
                {matieres.map((m) => (
                  <div key={m.classe_matiere_id} className="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                    <div>
                      <span className="block text-sm font-medium text-navy-800">{m.matiere}</span>
                      <span className="block text-xs text-navy-400">{m.classe}</span>
                    </div>
                    {sequence && (
                      <ImportExportBar
                        titreImport={t('notes.import_title')}
                        importUrl={`classe-matieres/${m.classe_matiere_id}/notes/import`}
                        exportUrl={`classe-matieres/${m.classe_matiere_id}/notes/export`}
                        modeleUrl={`classe-matieres/${m.classe_matiere_id}/notes/modele`}
                        colonnes={['Matricule', 'Nom complet', 'Note']}
                        nomFichier={`notes-${m.classe}-${m.matiere}`}
                        exportParams={{ sequence_id: sequence.id }}
                        extraFields={{ sequence_id: sequence.id }}
                        onImported={() => {}}
                      />
                    )}
                  </div>
                ))}
              </div>
            </Card>
          )}

          {competences && competences.length > 0 && (
            <Card>
              <h2 className="mb-3 font-display text-sm font-bold tracking-tight text-navy-900">
                {t('import_export_enseignant.competences')}
              </h2>
              <div className="flex flex-col divide-y divide-navy-50">
                {competences.map((c) => (
                  <div key={c.classe_competence_id} className="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                    <div>
                      <span className="block text-sm font-medium text-navy-800">{c.competence}</span>
                      <span className="block text-xs text-navy-400">{c.classe}</span>
                    </div>
                    {trimestre && (
                      <ImportExportBar
                        titreImport={t('notes.import_title')}
                        importUrl={`classe-competences/${c.classe_competence_id}/notes-primaire/import`}
                        exportUrl={`classe-competences/${c.classe_competence_id}/notes-primaire/export`}
                        modeleUrl={`classe-competences/${c.classe_competence_id}/notes-primaire/modele`}
                        colonnes={['Matricule', 'Nom complet', '…']}
                        nomFichier={`notes-${c.classe}-${c.competence}`}
                        exportParams={{ trimestre_id: trimestre.id }}
                        extraFields={{ trimestre_id: trimestre.id }}
                        onImported={() => {}}
                      />
                    )}
                  </div>
                ))}
              </div>
            </Card>
          )}

          {(!matieres || matieres.length === 0) && (!competences || competences.length === 0) && (
            <EmptyState label={t('import_export_enseignant.aucune_affectation')} />
          )}
        </>
      )}
    </div>
  )
}
