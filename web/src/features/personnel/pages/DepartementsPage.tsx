import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Building2, Plus } from 'lucide-react'
import { fetchDepartements, createDepartement, type Departement } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

export function DepartementsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const { data, isLoading, isError } = useQuery({ queryKey: ['departements'], queryFn: fetchDepartements })
  const [nom, setNom] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const handleAdd = async () => {
    if (!nom.trim()) return
    setSubmitting(true)
    try {
      await createDepartement(nom.trim())
      setNom('')
      queryClient.invalidateQueries({ queryKey: ['departements'] })
    } finally {
      setSubmitting(false)
    }
  }

  const colonnes: Colonne<Departement>[] = [
    {
      cle: 'nom',
      entete: t('personnel.departement'),
      valeur: (d) => d.nom,
      cellule: (d) => <span className="font-semibold text-navy-900">{d.nom}</span>,
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('nav.departements')} icon={Building2} />

      {can('personnel.manage') && (
        <div className="flex max-w-md gap-2">
          <Input value={nom} onChange={(e) => setNom(e.target.value)} placeholder={t('personnel.departement')} />
          <Button onClick={handleAdd} disabled={submitting}>
            <Plus className="h-4 w-4" />
            {t('common.add')}
          </Button>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data}
          cleLigne={(d) => d.id}
          placeholderRecherche="Rechercher un département…"
          messageVide="Aucun département pour cet établissement."
          largeurMin={320}
        />
      )}
    </div>
  )
}
