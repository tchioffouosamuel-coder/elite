import { ArrowLeft, ClipboardCheck } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { fetchAnciensReinscrits } from '@/features/dashboard/api'
import { Card } from '@/shared/ui/Card'
import { EmptyState, ErrorState, Spinner } from '@/shared/ui/Feedback'
import { PageHeader } from '@/shared/ui/PageHeader'

export function AnciensReinscritsPage() {
  const navigate = useNavigate()
  const { data: eleves = [], isLoading, isError } = useQuery({
    queryKey: ['dashboard', 'anciens-reinscrits'],
    queryFn: fetchAnciensReinscrits,
  })

  return (
    <div className="flex flex-col gap-5">
      <button
        type="button"
        onClick={() => navigate('/')}
        className="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
      >
        <ArrowLeft className="h-4 w-4" />
        Retour au tableau de bord
      </button>

      <PageHeader
        titre="Anciens élèves réinscrits"
        sousTitre="Élèves déjà scolarisés qui ont confirmé leur présence pour l'année scolaire active."
        icon={ClipboardCheck}
      />

      {isLoading ? (
        <Spinner />
      ) : isError ? (
        <ErrorState />
      ) : eleves.length === 0 ? (
        <EmptyState label="Aucun ancien élève réinscrit pour l'année active." />
      ) : (
        <Card className="p-0">
          <div className="flex items-center justify-between border-b border-navy-100 px-4 py-3 sm:px-5">
            <span className="text-sm font-semibold text-navy-700">Liste des réinscrits</span>
            <span className="text-sm font-semibold tabular-nums text-navy-500">{eleves.length}</span>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[620px] text-left text-sm">
              <thead className="bg-cream-50 text-xs font-semibold uppercase tracking-wide text-navy-400">
                <tr>
                  <th className="px-4 py-3 sm:px-5">Élève</th>
                  <th className="px-4 py-3">Matricule</th>
                  <th className="px-4 py-3">Classe</th>
                  <th className="px-4 py-3">Sexe</th>
                  <th className="px-4 py-3">Statut</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-navy-50">
                {eleves.map((eleve) => (
                  <tr
                    key={eleve.id}
                    onClick={() => navigate(`/eleves/${eleve.id}`)}
                    className="cursor-pointer text-navy-700 transition-colors hover:bg-cream-50"
                  >
                    <td className="px-4 py-3 font-semibold text-navy-800 sm:px-5">{eleve.nom_complet}</td>
                    <td className="px-4 py-3 tabular-nums">{eleve.matricule ?? '—'}</td>
                    <td className="px-4 py-3">{eleve.classe?.nom ?? '—'}</td>
                    <td className="px-4 py-3">{eleve.sexe === 'F' ? 'Fille' : 'Garçon'}</td>
                    <td className="px-4 py-3 text-green-600">Réinscrit</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  )
}