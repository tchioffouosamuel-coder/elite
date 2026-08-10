import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, School } from 'lucide-react'
import { fetchClasses, type Classe } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ClasseFormModal } from '@/features/classes/pages/ClasseFormModal'

export function ClassesListPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [showForm, setShowForm] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })

  const colonnes: Colonne<Classe>[] = [
    {
      cle: 'nom',
      entete: t('classes.nom'),
      valeur: (c) => c.nom,
      cellule: (c) => <span className="font-semibold text-navy-900">{c.nom}</span>,
    },
    {
      cle: 'niveau',
      entete: t('classes.niveau'),
      valeur: (c) => c.niveau?.name_fr,
      cellule: (c) => (c.niveau ? <Badge tone="gold">{c.niveau.name_fr}</Badge> : '—'),
    },
    {
      cle: 'filiere',
      entete: t('classes.filiere'),
      valeur: (c) => c.filiere,
      cellule: (c) => c.filiere ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'effectif',
      entete: t('classes.effectif'),
      valeur: (c) => c.effectif ?? 0,
      cellule: (c) => (
        <span className="tabular-nums">
          <span className="font-semibold">{c.effectif ?? 0}</span>
          {c.capacite && <span className="text-navy-300"> / {c.capacite}</span>}
        </span>
      ),
    },
    {
      cle: 'professeur_principal',
      entete: t('classes.professeur_principal'),
      valeur: (c) => c.professeur_principal?.nom_complet,
      cellule: (c) => c.professeur_principal?.nom_complet ?? '—',
      masquerMobile: true,
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('classes.title')}
        icon={School}
        actions={
          can('classes.manage') && (
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              {t('classes.add')}
            </Button>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data}
          cleLigne={(c) => c.id}
          placeholderRecherche="Rechercher une classe…"
          messageVide="Aucune classe pour cet établissement."
          onLigneClick={(c) => navigate(`/classes/${c.id}`)}
        />
      )}

      {showForm && (
        <ClasseFormModal
          onClose={() => setShowForm(false)}
          onCreated={() => {
            setShowForm(false)
            queryClient.invalidateQueries({ queryKey: ['classes'] })
          }}
        />
      )}
    </div>
  )
}
