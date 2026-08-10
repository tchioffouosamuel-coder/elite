import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { ClasseFormModal } from '@/features/classes/pages/ClasseFormModal'

export function ClassesListPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-semibold text-navy-900">{t('classes.title')}</h1>
        {can('classes.manage') && (
          <Button onClick={() => setShowForm(true)}>
            <Plus className="h-4 w-4" />
            {t('classes.add')}
          </Button>
        )}
      </div>

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState />
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {data.map((c) => (
            <Link key={c.id} to={`/classes/${c.id}`}>
              <Card className="flex h-full flex-col gap-2 transition-shadow hover:shadow-lifted">
                <div className="flex items-start justify-between">
                  <h3 className="font-display text-lg font-semibold text-navy-800">{c.nom}</h3>
                  <span className="rounded-full bg-cream-100 px-2.5 py-0.5 text-xs font-semibold text-navy-500">
                    {c.niveau?.name_fr}
                  </span>
                </div>
                {c.filiere && <p className="text-sm text-navy-400">{c.filiere}</p>}
                <div className="mt-1 flex items-center justify-between text-sm">
                  <span className="text-navy-500">
                    {t('classes.effectif')} : <span className="font-semibold text-navy-800">{c.effectif ?? 0}</span>
                    {c.capacite && <span className="text-navy-300"> / {c.capacite}</span>}
                  </span>
                </div>
                {c.professeur_principal && (
                  <p className="text-xs text-navy-400">
                    {t('classes.professeur_principal')} : {c.professeur_principal.nom_complet}
                  </p>
                )}
              </Card>
            </Link>
          ))}
        </div>
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
