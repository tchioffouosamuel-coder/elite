import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Building2, Plus, Eye } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { fetchDepartements, createDepartement, type Departement } from '@/features/personnel/api'
import { fetchSchools } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

export function DepartementsPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const { data, isLoading, isError } = useQuery({ queryKey: ['departements'], queryFn: fetchDepartements })
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
  const [nom, setNom] = useState('')
  const [schoolId, setSchoolId] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const handleAdd = async () => {
    if (!nom.trim()) return
    setSubmitting(true)
    try {
      await createDepartement(nom.trim(), schoolId ? Number(schoolId) : null)
      setNom('')
      setSchoolId('')
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
    {
      cle: 'school',
      entete: t('classes.ecole'),
      valeur: (d) => d.school?.name,
      cellule: (d) => <span className="text-navy-600">{d.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'chef',
      entete: t('departements.chef'),
      valeur: (d) => d.head_personnel?.nom_complet || '—',
      cellule: (d) => <span className="text-navy-700">{d.head_personnel?.nom_complet || '—'}</span>,
    },
    {
      cle: 'matieres',
      entete: t('departements.matieres'),
      valeur: (d) => (d.matieres?.length || 0).toString(),
      cellule: (d) => <span className="text-navy-700">{t('departements.matieres_count', { count: d.matieres?.length || 0 })}</span>,
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (d) => (
        <button
          onClick={() => navigate(`/departements/${d.id}`)}
          className="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium text-gold-600 hover:bg-gold-50"
        >
          <Eye className="h-4 w-4" />
          {t('departements.details')}
        </button>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('nav.departements')} icon={Building2} />

      {can('personnel.manage') && (
        <div className="flex max-w-xl gap-2">
          {(schools?.length ?? 0) > 1 && (
            <Select value={schoolId} onChange={(e) => setSchoolId(e.target.value)} className="max-w-48">
              <option value="">{t('classes.ecole')}</option>
              {schools?.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          )}
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
          placeholderRecherche={t('departements.search_placeholder')}
          messageVide={t('departements.empty')}
          largeurMin={320}
        />
      )}
    </div>
  )
}
