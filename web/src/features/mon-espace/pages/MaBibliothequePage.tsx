import { useQuery } from '@tanstack/react-query'
import { Library } from 'lucide-react'
import { fetchMaBibliotheque } from '@/features/mon-espace/api'
import { BibliothequeListeLecture } from '@/features/bibliotheque/BibliothequeListeLecture'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

/** Bibliothèque numérique, en lecture seule : documents visibles pour l'école de l'employé. */
export function MaBibliothequePage() {
  const { data, isLoading, isError } = useQuery({ queryKey: ['mon-espace-bibliotheque'], queryFn: fetchMaBibliotheque })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Ma bibliothèque"
        sousTitre="Documents partagés par l'établissement."
        icon={Library}
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <BibliothequeListeLecture documents={data} messageVide="Aucun document pour l'instant." />
      )}
    </div>
  )
}
