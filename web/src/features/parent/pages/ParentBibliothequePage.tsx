import { useQuery } from '@tanstack/react-query'
import { Library } from 'lucide-react'
import { fetchMaBibliotheque } from '@/features/parent/api'
import { BibliothequeListeLecture } from '@/features/bibliotheque/BibliothequeListeLecture'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

/** Bibliothèque numérique, en lecture seule : documents des écoles des enfants du compte connecté. */
export function ParentBibliothequePage() {
  const { data, isLoading, isError } = useQuery({ queryKey: ['parent-bibliotheque'], queryFn: fetchMaBibliotheque })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Bibliothèque / Library"
        sousTitre="Documents partagés par l'établissement. / Documents shared by the school."
        icon={Library}
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <BibliothequeListeLecture documents={data} messageVide="Aucun document pour l'instant. / No document yet." />
      )}
    </div>
  )
}
