import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Layers, GitBranch } from 'lucide-react'
import { fetchMonNiveau, type AffectationNiveau } from '@/features/enseignant/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { modifierAttributionCompetence } from '@/features/pedagogie/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card, StatCard } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

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

/** Niveau scolaire animé (primaire/maternelle) : ses affectations de compétences, modifiables, et son taux de remplissage. */
export function MonNiveauPage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [enModification, setEnModification] = useState<number | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['mon-niveau'], queryFn: fetchMonNiveau })
  const { data: personnels } = useQuery({ queryKey: ['personnels'], queryFn: () => fetchPersonnels() })
  const enseignants = personnels?.filter((p) => p.fonction?.toLowerCase().includes('enseignant')) ?? []

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  const changerEnseignant = async (affectation: AffectationNiveau, personnelId: number | null) => {
    try {
      await modifierAttributionCompetence(affectation.classe_competence_id, { personnel_id: personnelId })
      succes('Affectation mise à jour.')
      setEnModification(null)
      queryClient.invalidateQueries({ queryKey: ['mon-niveau'] })
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={data.niveau.libelle} sousTitre="Affectations et suivi pédagogique de mon niveau." icon={Layers} />

      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <StatCard label="Affectations" value={data.affectations.length} icon={GitBranch} accent="gold" />
        <StatCard
          label="Remplissage moyen"
          value={data.taux_remplissage_moyen === null ? '—' : `${data.taux_remplissage_moyen}%`}
          accent="green"
        />
      </div>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Affectations</h2>
        {data.affectations.length === 0 ? (
          <p className="text-sm text-navy-400">Aucune affectation dans ce niveau.</p>
        ) : (
          <Table>
            <Thead>
              <tr>
                <Th>{t('competences.title')}</Th>
                <Th>{t('eleves.classe')}</Th>
                <Th>{t('personnel.enseignant')}</Th>
                <Th>Remplissage</Th>
              </tr>
            </Thead>
            <tbody>
              {data.affectations.map((affectation) => (
                <Tr key={affectation.classe_competence_id}>
                  <Td className="font-medium">{affectation.competence}</Td>
                  <Td>{affectation.classe}</Td>
                  <Td>
                    {enModification === affectation.classe_competence_id ? (
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
                        onClick={() => setEnModification(affectation.classe_competence_id)}
                        className="text-sm text-navy-700 underline decoration-navy-200 underline-offset-2 hover:text-navy-900"
                      >
                        {affectation.enseignant ?? 'Non affecté'}
                      </button>
                    )}
                  </Td>
                  <Td>
                    <Jauge taux={affectation.taux_remplissage} />
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
