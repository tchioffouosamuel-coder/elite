import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { FileSpreadsheet } from 'lucide-react'
import { fetchPersonnels } from '@/features/personnel/api'
import { fetchTrimestresPourClasse } from '@/features/pedagogie/api'
import { fetchAffectationsMatieresDe, fetchAffectationsCompetencesDe } from '@/features/resultats/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'
import { useAuthStore } from '@/shared/store/authStore'

/**
 * Point 17 de la punch-list : import/export des résultats par enseignant —
 * la direction choisit un agent et retrouve, en un seul écran, le modèle
 * Excel / l'export / l'import de toutes ses affectations dans un seul fichier.
 * Réservé à `pedagogie.suivi_saisies`, même privilège que le tableau
 * de suivi des saisies (cf. route côté API).
 */
export function ImportExportEnseignantPage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const can = useAuthStore((s) => s.can)
  const [personnelId, setPersonnelId] = useState<number | ''>('')
  const [trimestreId, setTrimestreId] = useState<number | ''>('')
  const [sequenceId, setSequenceId] = useState<number | ''>('')

  const { data: personnels, isLoading: chargementPersonnels } = useQuery({
    queryKey: ['personnels', 'import-export'],
    queryFn: () => fetchPersonnels({ per_page: 500, statut: 'actif' }),
  })
  const enseignant = personnels?.find((p) => p.id === personnelId)
  const secondaire = enseignant?.school?.type === 'secondaire'
  const primaire = enseignant?.school?.type === 'primaire' || enseignant?.school?.type === 'maternelle'

  const { data: affectationsMatieres, isLoading: chargementMatieres } = useQuery({
    queryKey: ['affectations-matieres-de', personnelId],
    queryFn: () => fetchAffectationsMatieresDe(Number(personnelId)),
    enabled: !!personnelId && secondaire,
  })
  const matieres = secondaire ? affectationsMatieres : undefined

  const { data: competences, isLoading: chargementCompetences } = useQuery({
    queryKey: ['affectations-competences-de', personnelId],
    queryFn: () => fetchAffectationsCompetencesDe(Number(personnelId)),
    enabled: !!personnelId && primaire,
  })

  const classeId = (secondaire ? matieres?.[0] : primaire ? competences?.[0] : undefined)?.classe_id
  const { data: trimestres, isLoading: chargementTrimestres } = useQuery({
    queryKey: ['trimestres', 'import-export-enseignant', classeId],
    queryFn: () => fetchTrimestresPourClasse(Number(classeId)),
    enabled: !!classeId,
  })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.find((tr) => tr.annee_active) ?? trimestres?.[0]
  const trimestre = trimestres?.find((tr) => tr.id === (trimestreId || trimestreActif?.id))
  const sequence = trimestre?.sequences.find((s) => s.id === (sequenceId || trimestre.sequences[0]?.id))
  const parametres: Record<string, number> | null = secondaire
    ? sequence ? { sequence_id: sequence.id } : null
    : primaire && trimestre ? { trimestre_id: trimestre.id } : null
  const affectations = secondaire ? matieres : primaire ? competences : undefined
  const chargement = chargementMatieres || chargementCompetences || chargementTrimestres

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
            onChange={(e) => {
              setPersonnelId(e.target.value ? Number(e.target.value) : '')
              setTrimestreId('')
              setSequenceId('')
            }}
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

          {secondaire && trimestre && trimestre.sequences.length > 0 && (
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
          {affectations && affectations.length > 0 && (
            <Card>
              <h2 className="mb-2 font-display text-sm font-bold text-navy-900">{t('import_export_enseignant.fichier_unique')}</h2>
              <p className="mb-4 text-sm text-navy-500">{t(`import_export_enseignant.${secondaire ? 'format_secondaire' : 'format_primaire'}`)}</p>
              {parametres ? (
                <div className="flex flex-wrap items-center gap-2">
                  <ImportExportBar
                    key={`${personnelId}-${secondaire ? sequence?.id : trimestre?.id}`}
                    titreImport={t('import_export_enseignant.title')}
                    importUrl={`personnels/${personnelId}/notes/import`}
                    exportUrl={`personnels/${personnelId}/notes/export`}
                    modeleUrl={`personnels/${personnelId}/notes/modele`}
                    colonnes={secondaire ? ['Matricule', 'Nom complet', 'Note'] : ['Matricule', 'Nom complet', t('import_export_enseignant.competences')]}
                    nomFichier={`notes-${enseignant?.nom_complet ?? personnelId}`}
                    exportParams={parametres}
                    extraFields={parametres}
                    note={t(`import_export_enseignant.${secondaire ? 'format_secondaire' : 'format_primaire'}`)}
                    peutImporter={can('notes.create')}
                    peutExporter={can('notes.view')}
                    onImported={() => {
                      queryClient.invalidateQueries({ queryKey: ['affectations-matieres-de', personnelId] })
                      queryClient.invalidateQueries({ queryKey: ['affectations-competences-de', personnelId] })
                      queryClient.invalidateQueries({ queryKey: ['grille-notes'] })
                      queryClient.invalidateQueries({ queryKey: ['grille-primaire'] })
                    }}
                  />
                </div>
              ) : <p className="text-sm text-navy-400">{t('import_export_enseignant.aucune_periode')}</p>}
            </Card>
          )}
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
                  </div>
                ))}
              </div>
            </Card>
          )}

          {primaire && competences && competences.length > 0 && (
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
                  </div>
                ))}
              </div>
            </Card>
          )}

          {(!affectations || affectations.length === 0) && (
            <EmptyState label={t('import_export_enseignant.aucune_affectation')} />
          )}
        </>
      )}
    </div>
  )
}
