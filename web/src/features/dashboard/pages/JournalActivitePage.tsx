import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, ListChecks } from 'lucide-react'
import { fetchActiviteRecente, type ActiviteLog } from '@/features/dashboard/api'
import { LigneActivite } from '@/features/dashboard/pages/LigneActivite'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

/** Journal complet, chargé par pages successives (« Charger plus »). */
export function JournalActivitePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [lignes, setLignes] = useState<ActiviteLog[]>([])
  const [derniereePage, setDerniereePage] = useState<number | null>(null)

  const { data, isFetching, isError } = useQuery({
    queryKey: ['dashboard-activite', page],
    queryFn: () => fetchActiviteRecente(page),
  })

  // Chaque page chargée s'ajoute aux précédentes plutôt que de les remplacer
  // — même principe qu'un défilement infini, mais déclenché par un clic pour
  // rester prévisible sur une liste qui peut être longue.
  useEffect(() => {
    if (!data) return
    setLignes((l) => (page === 1 ? data.items : [...l, ...data.items]))
    setDerniereePage(data.pagination.last_page)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data])

  const encoreDesPages = derniereePage !== null && page < derniereePage

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('dashboard.activity_journal_title')}
        icon={ListChecks}
        actions={
          <Button type="button" variant="secondary" onClick={() => navigate(-1)}>
            <ArrowLeft className="h-4 w-4" />
            Retour
          </Button>
        }
      />

      <Card>
        <div className="flex flex-col gap-1">
          {lignes.length === 0 && isFetching ? (
            <Spinner />
          ) : isError ? (
            <ErrorState />
          ) : (
            <ul className="flex flex-col divide-y divide-navy-50">
              {lignes.map((a, i) => (
                <LigneActivite key={i} a={a} />
              ))}
            </ul>
          )}

          {lignes.length > 0 && (
            <div className="mt-3 flex justify-center">
              {encoreDesPages ? (
                <Button type="button" variant="secondary" size="sm" onClick={() => setPage((p) => p + 1)} disabled={isFetching}>
                  {isFetching ? t('common.loading') : t('dashboard.load_more')}
                </Button>
              ) : (
                <p className="text-xs text-navy-400">{t('dashboard.no_more_activity')}</p>
              )}
            </div>
          )}
        </div>
      </Card>
    </div>
  )
}
