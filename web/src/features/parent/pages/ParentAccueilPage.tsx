import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { ChevronRight, Users } from 'lucide-react'
import { fetchMesEnfants } from '@/features/parent/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'

function initiales(nom: string) {
  return nom
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

/** Porte d'entrée du portail : un enfant, une carte — tout ce qui le concerne est derrière. */
export function ParentAccueilPage() {
  const navigate = useNavigate()
  const { data: enfants, isLoading, isError } = useQuery({ queryKey: ['parent-enfants'], queryFn: fetchMesEnfants })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre="Mes enfants" sousTitre="Sélectionnez un enfant pour voir tout ce qui le concerne." icon={Users} />

      {isLoading ? (
        <Spinner />
      ) : isError || !enfants ? (
        <ErrorState />
      ) : enfants.length === 0 ? (
        <EmptyState label="Aucun enfant rattaché à votre compte pour l'instant." />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {enfants.map((e) => (
            <div key={e.id} onClick={() => navigate(`/parent/enfants/${e.id}`)} className="cursor-pointer">
              <Card className="transition-shadow hover:shadow-lifted">
                <div className="flex items-center gap-4">
                {e.photo_url ? (
                  <img src={e.photo_url} alt={e.nom_complet} className="h-14 w-14 flex-none rounded-full object-cover ring-1 ring-navy-100" />
                ) : (
                  <span className="flex h-14 w-14 flex-none items-center justify-center rounded-full bg-navy-700 text-lg font-bold text-cream-50">
                    {initiales(e.nom_complet)}
                  </span>
                )}
                <div className="min-w-0 flex-1">
                  <p className="truncate font-display text-base font-bold text-navy-900">{e.nom_complet}</p>
                  <p className="truncate text-sm text-navy-400">
                    {[e.classe?.nom, e.school?.name].filter(Boolean).join(' · ') || '—'}
                  </p>
                </div>
                  <ChevronRight className="h-5 w-5 flex-none text-navy-300" />
                </div>
              </Card>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
