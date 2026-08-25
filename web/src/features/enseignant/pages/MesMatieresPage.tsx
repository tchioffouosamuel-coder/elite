import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { BookOpen, ClipboardList, GitBranch, FileDown } from 'lucide-react'
import { fetchMesAffectationsActives } from '@/features/pedagogie/api'
import { ouvrirFicheProgressionPdf } from '@/features/progression/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/** Table des matières que j'enseigne, avec accès direct à la saisie des notes et à ma progression. */
export function MesMatieresPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const { data: affectations, isLoading, isError } = useQuery({
    queryKey: ['enseignant-mes-matieres'],
    queryFn: fetchMesAffectationsActives,
  })

  const telechargerBilan = async (classeMatiereId: number) => {
    try {
      await ouvrirFicheProgressionPdf(classeMatiereId)
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre="Mes matières" sousTitre="Les matières que j'enseigne, classe par classe." icon={BookOpen} />

      {isLoading ? (
        <Spinner />
      ) : isError || !affectations ? (
        <ErrorState />
      ) : affectations.length === 0 ? (
        <EmptyState label="Aucune matière ne vous est affectée pour l'instant." />
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>{t('matieres.title')}</Th>
              <Th>{t('eleves.classe')}</Th>
              <Th>Remplissage des notes</Th>
              <Th className="text-center">{t('common.actions')}</Th>
            </tr>
          </Thead>
          <tbody>
            {affectations.map((affectation) => (
              <Tr key={affectation.classe_matiere_id}>
                <Td className="font-medium">{affectation.matiere}</Td>
                <Td>{affectation.classe}</Td>
                <Td>
                  {affectation.taux_remplissage === null ? (
                    <span className="text-xs text-navy-300">—</span>
                  ) : (
                    <div className="flex items-center gap-2">
                      <div className="h-2 w-24 overflow-hidden rounded-full bg-navy-100">
                        <div className="h-full rounded-full bg-gold-500" style={{ width: `${affectation.taux_remplissage}%` }} />
                      </div>
                      <span className="text-xs font-semibold tabular-nums text-navy-600">{affectation.taux_remplissage}%</span>
                    </div>
                  )}
                </Td>
                <Td className="text-center">
                  <div className="flex flex-wrap items-center justify-center gap-1">
                    <button
                      onClick={() => navigate(`/enseignant/mes-matieres/${affectation.classe_matiere_id}/notes`)}
                      className="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-navy-600 hover:bg-navy-50 transition-colors"
                    >
                      <ClipboardList className="h-3.5 w-3.5" />
                      Remplir les notes
                    </button>
                    <button
                      onClick={() => navigate(`/progression/matieres/${affectation.classe_matiere_id}`)}
                      className="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-navy-600 hover:bg-navy-50 transition-colors"
                    >
                      <GitBranch className="h-3.5 w-3.5" />
                      Progression
                    </button>
                    <button
                      onClick={() => telechargerBilan(affectation.classe_matiere_id)}
                      className="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-navy-600 hover:bg-navy-50 transition-colors"
                    >
                      <FileDown className="h-3.5 w-3.5" />
                      Bilan PDF
                    </button>
                  </div>
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}
