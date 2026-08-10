import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { BookOpen, Plus } from 'lucide-react'
import { fetchMatieres, createMatiere } from '@/features/pedagogie/api'
import { fetchDepartements } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { useForm } from 'react-hook-form'
import type { Matiere, MatierePayload } from '@/features/pedagogie/api'

export function MatieresPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['matieres'], queryFn: fetchMatieres })
  const { data: departements } = useQuery({ queryKey: ['departements'], queryFn: fetchDepartements })

  const { register, handleSubmit, reset } = useForm<MatierePayload>()

  const onSubmit = async (values: MatierePayload) => {
    await createMatiere({ ...values, departement_id: values.departement_id ? Number(values.departement_id) : null })
    reset()
    setShowForm(false)
    queryClient.invalidateQueries({ queryKey: ['matieres'] })
  }

  const colonnes: Colonne<Matiere>[] = [
    {
      cle: 'nom',
      entete: t('matieres.nom'),
      valeur: (m) => m.nom,
      cellule: (m) => <span className="font-semibold text-navy-900">{m.nom}</span>,
    },
    {
      cle: 'abbreviation',
      entete: t('matieres.abbreviation'),
      valeur: (m) => m.abbreviation,
      cellule: (m) => m.abbreviation ?? '—',
    },
    {
      cle: 'departement',
      entete: t('personnel.departement'),
      valeur: (m) => m.departement?.nom,
      cellule: (m) => m.departement?.nom ?? '—',
      masquerMobile: true,
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('matieres.title')}
        icon={BookOpen}
        actions={
          can('pedagogie.manage') && (
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              {t('matieres.add')}
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
          cleLigne={(m) => m.id}
          placeholderRecherche="Rechercher une matière…"
          messageVide="Aucune matière pour cet établissement."
        />
      )}

      {showForm && (
        <Modal title={t('matieres.add')} onClose={() => setShowForm(false)}>
          <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
            <Input label={t('matieres.nom')} {...register('nom', { required: true })} />
            <Input label={t('matieres.abbreviation')} {...register('abbreviation')} />
            <Select label={t('personnel.departement')} {...register('departement_id')}>
              <option value="">—</option>
              {departements?.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.nom}
                </option>
              ))}
            </Select>
            <div className="mt-2 flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>
                {t('common.cancel')}
              </Button>
              <Button type="submit">{t('common.save')}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
