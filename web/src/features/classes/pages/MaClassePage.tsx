import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { School } from 'lucide-react'
import { fetchMaClasse } from '@/features/classes/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { EmptyState, Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ClasseTabs } from '@/features/classes/pages/ClasseTabs'

/** Vue du titulaire sur sa propre classe : pas de liste à parcourir, pas de retour. */
export function MaClassePage() {
  const { t } = useTranslation()

  const { data: classe, isLoading, isError } = useQuery({ queryKey: ['ma-classe'], queryFn: fetchMaClasse })

  if (isLoading) return <Spinner />
  if (isError) return <ErrorState />

  if (!classe) {
    return (
      <div className="flex flex-col gap-5">
        <PageHeader titre={t('classes.maClasse')} icon={School} />
        <Card>
          <EmptyState label={t('classes.aucune_classe_confiee')} />
        </Card>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={classe.nom}
        sousTitre={[classe.niveau_scolaire?.libelle ?? classe.niveau?.name_fr, classe.filiere].filter(Boolean).join(' · ') || undefined}
        icon={School}
      />

      <ClasseTabs classe={classe} />
    </div>
  )
}
