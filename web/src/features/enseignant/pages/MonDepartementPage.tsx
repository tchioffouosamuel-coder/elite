import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Building2, Download, GitBranch } from 'lucide-react'
import { fetchMonDepartement, type AffectationDepartement } from '@/features/enseignant/api'
import { fetchStatsPedagogiquesParDepartement, exportStatistiquesAsPdf, fetchPersonnels } from '@/features/personnel/api'
import { modifierAffectation } from '@/features/pedagogie/api'
import { ouvrirFicheProgressionPdf } from '@/features/progression/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card, StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Select } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/** Barre d'avancement, lue d'un coup d'œil dans un tableau. */
function Jauge({ taux }: { taux: number | null }) {
  if (taux === null) return <span className="text-xs text-navy-300">—</span>
  return (
    <div className="flex items-center gap-2">
      <div className="h-2 w-24 overflow-hidden rounded-full bg-navy-100">
        <div className="h-full rounded-full bg-gold-500" style={{ width: `${taux}%` }} />
      </div>
      <span className="text-xs font-semibold tabular-nums text-navy-600">{taux}%</span>
    </div>
  )
}

/** Département dirigé : ses affectations, modifiables, et ses bilans pédagogiques. */
export function MonDepartementPage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [exporting, setExporting] = useState(false)
  const [enModification, setEnModification] = useState<number | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['mon-departement'], queryFn: fetchMonDepartement })
  const { data: stats } = useQuery({
    queryKey: ['mon-departement-stats', data?.departement.id],
    queryFn: () => fetchStatsPedagogiquesParDepartement(data!.departement.id),
    enabled: !!data,
  })
  const { data: personnels } = useQuery({ queryKey: ['personnels'], queryFn: () => fetchPersonnels() })
  const enseignants = personnels?.filter((p) => p.fonction?.toLowerCase().includes('enseignant')) ?? []

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  const telechargerBilanDepartement = async () => {
    setExporting(true)
    try {
      await exportStatistiquesAsPdf(data.departement.id, undefined, data.departement.nom)
    } catch {
      erreur("Impossible de générer le bilan du département.")
    } finally {
      setExporting(false)
    }
  }

  const telechargerBilanMatiere = async (classeMatiereId: number) => {
    try {
      await ouvrirFicheProgressionPdf(classeMatiereId)
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  const changerEnseignant = async (affectation: AffectationDepartement, personnelId: number | null) => {
    try {
      await modifierAffectation(affectation.classe_matiere_id, { personnel_id: personnelId })
      succes('Affectation mise à jour.')
      setEnModification(null)
      queryClient.invalidateQueries({ queryKey: ['mon-departement'] })
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={data.departement.nom}
        sousTitre="Affectations et suivi pédagogique de mon département."
        icon={Building2}
        actions={
          <Button variant="secondary" onClick={telechargerBilanDepartement} disabled={exporting}>
            <Download className="h-4 w-4" />
            Bilan du département
          </Button>
        }
      />

      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <StatCard label="Matières" value={data.matieres.length} icon={Building2} accent="navy" />
        <StatCard label="Affectations" value={data.affectations.length} icon={GitBranch} accent="gold" />
        <StatCard
          label="Remplissage moyen"
          value={data.taux_remplissage_moyen === null ? '—' : `${data.taux_remplissage_moyen}%`}
          accent="green"
        />
        <StatCard
          label={t('departements.detail.moyenne_generale')}
          value={stats?.stats_consolidees.moyenne_generale?.toFixed(2) ?? '—'}
          accent="navy"
        />
      </div>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Affectations</h2>
        {data.affectations.length === 0 ? (
          <p className="text-sm text-navy-400">Aucune affectation dans ce département.</p>
        ) : (
          <Table>
            <Thead>
              <tr>
                <Th>{t('matieres.title')}</Th>
                <Th>{t('eleves.classe')}</Th>
                <Th>{t('personnel.enseignant')}</Th>
                <Th>Remplissage</Th>
                <Th className="text-center">{t('common.actions')}</Th>
              </tr>
            </Thead>
            <tbody>
              {data.affectations.map((affectation) => (
                <Tr key={affectation.classe_matiere_id}>
                  <Td className="font-medium">{affectation.matiere}</Td>
                  <Td>{affectation.classe}</Td>
                  <Td>
                    {enModification === affectation.classe_matiere_id ? (
                      <Select
                        defaultValue={affectation.personnel_id ?? ''}
                        onChange={(e) => changerEnseignant(affectation, e.target.value ? Number(e.target.value) : null)}
                        className="min-w-48"
                      >
                        <option value="">—</option>
                        {enseignants.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.nom_complet}
                          </option>
                        ))}
                      </Select>
                    ) : (
                      <button
                        onClick={() => setEnModification(affectation.classe_matiere_id)}
                        className="text-sm text-navy-700 underline decoration-navy-200 underline-offset-2 hover:text-navy-900"
                      >
                        {affectation.enseignant ?? 'Non affecté'}
                      </button>
                    )}
                  </Td>
                  <Td>
                    <Jauge taux={affectation.taux_remplissage} />
                  </Td>
                  <Td className="text-center">
                    <button
                      onClick={() => telechargerBilanMatiere(affectation.classe_matiere_id)}
                      className="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-navy-600 hover:bg-navy-50 transition-colors"
                    >
                      <Download className="h-3.5 w-3.5" />
                      Bilan PDF
                    </button>
                  </Td>
                </Tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>
    </div>
  )
}
