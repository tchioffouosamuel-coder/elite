import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Archive as ArchiveIcon, ChevronRight, Users } from 'lucide-react'
import { fetchAnneesArchivees, fetchClassesArchivees } from '@/features/archives/api'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'

/**
 * Archives des années scolaires closes : chaque classe archivée garde son
 * roster, ses décisions du conseil de fin d'année, et son détail pédagogique
 * (notes, absences, discipline, infirmerie) figé au moment de l'archivage —
 * jamais recalculé, cf. ArchivageService côté API.
 */
export function ArchivesPage() {
  const navigate = useNavigate()
  const [anneeId, setAnneeId] = useState<number | null>(null)

  const { data: annees, isLoading: loadingAnnees } = useQuery({ queryKey: ['archives-annees'], queryFn: fetchAnneesArchivees })
  const { data: classes, isLoading: loadingClasses } = useQuery({
    queryKey: ['archives-classes', anneeId],
    queryFn: () => fetchClassesArchivees(anneeId!),
    enabled: anneeId !== null,
  })

  useEffect(() => {
    if (anneeId !== null || !annees || annees.length === 0) return
    setAnneeId(annees[0].id)
  }, [annees, anneeId])

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">Archives</h1>
        <p className="text-sm text-navy-400">Années scolaires closes — consultation en lecture seule.</p>
      </div>

      <Card>
        {loadingAnnees ? (
          <Spinner />
        ) : !annees || annees.length === 0 ? (
          <EmptyState label="Aucune année scolaire archivée pour l'instant." />
        ) : (
          <div className="max-w-xs">
            <Select label="Année scolaire" value={anneeId ?? ''} onChange={(e) => setAnneeId(Number(e.target.value))}>
              {annees.map((a) => (
                <option key={a.id} value={a.id}>{a.libelle}</option>
              ))}
            </Select>
          </div>
        )}
      </Card>

      {anneeId !== null && (
        <Card>
          <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Classes archivées</h2>
          {loadingClasses ? (
            <Spinner />
          ) : !classes || classes.length === 0 ? (
            <EmptyState label="Aucune classe archivée pour cette année." />
          ) : (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {classes.map((c) => (
                <button
                  key={c.id}
                  onClick={() => navigate(`/archives/${anneeId}/classes/${c.classe_id ?? c.id}`)}
                  className="flex items-center justify-between gap-3 rounded-xl border border-navy-100 bg-white p-4 text-left transition-colors hover:bg-cream-50"
                >
                  <div className="flex items-center gap-3">
                    <span className="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-navy-50 text-navy-500">
                      <ArchiveIcon className="h-4 w-4" />
                    </span>
                    <div>
                      <p className="text-sm font-semibold text-navy-800">{c.classe_nom}</p>
                      <p className="flex items-center gap-1 text-xs text-navy-400">
                        <Users className="h-3 w-3" />
                        {c.effectif} élève{c.effectif > 1 ? 's' : ''}
                      </p>
                    </div>
                  </div>
                  <ChevronRight className="h-4 w-4 flex-none text-navy-300" />
                </button>
              ))}
            </div>
          )}
        </Card>
      )}
    </div>
  )
}
