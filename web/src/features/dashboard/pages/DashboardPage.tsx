import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { UserRound, Users, GraduationCap, School, UserPlus, BriefcaseBusiness } from 'lucide-react'
import { fetchDashboardStats } from '@/features/dashboard/api'
import { StatCard, Card } from '@/shared/ui/Card'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

export function DashboardPage() {
  const { t } = useTranslation()
  const { data, isLoading, isError } = useQuery({ queryKey: ['dashboard'], queryFn: fetchDashboardStats })

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  const { effectifs, repartition_genre, top_classes, indicateurs, activite_recente, annee_scolaire_active } = data
  const maxClasseEffectif = Math.max(1, ...top_classes.map((c) => c.effectif))
  const totalGenre = Math.max(1, repartition_genre.garcons + repartition_genre.filles)
  const partGarcons = Math.round((repartition_genre.garcons / totalGenre) * 100)

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('dashboard.title')}</h1>
        {annee_scolaire_active && (
          <p className="mt-1 text-sm text-navy-400">
            {t('dashboard.active_year')} : <span className="font-semibold text-navy-600">{annee_scolaire_active}</span>
          </p>
        )}
      </div>

      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <StatCard label={t('dashboard.students')} value={effectifs.eleves} icon={UserRound} accent="navy" />
        <StatCard label={t('dashboard.staff')} value={effectifs.personnel} icon={Users} accent="green" />
        <StatCard label={t('dashboard.teachers')} value={effectifs.enseignants} icon={GraduationCap} accent="green" />
        <StatCard label={t('dashboard.classes')} value={effectifs.classes} icon={School} accent="gold" />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <h2 className="mb-5 font-display text-base font-bold tracking-tight text-navy-800">{t('dashboard.top_classes')}</h2>
          <div className="flex flex-col gap-4">
            {top_classes.map((c) => (
              <div key={c.classe} className="flex items-center gap-3">
                <span className="w-24 flex-none truncate text-sm font-medium text-navy-600">{c.classe}</span>
                <div className="h-2.5 flex-1 rounded-full bg-cream-100">
                  <div
                    className="h-2.5 rounded-full bg-gradient-to-r from-gold-500 to-gold-300 transition-all"
                    style={{ width: `${(c.effectif / maxClasseEffectif) * 100}%` }}
                  />
                </div>
                <span className="w-8 flex-none text-right text-sm font-semibold tabular-nums text-navy-700">{c.effectif}</span>
              </div>
            ))}
          </div>
        </Card>

        <Card>
          <h2 className="mb-5 font-display text-base font-bold tracking-tight text-navy-800">{t('dashboard.gender_distribution')}</h2>

          <div className="flex h-2.5 overflow-hidden rounded-full bg-cream-100">
            <div className="h-full bg-navy-600" style={{ width: `${partGarcons}%` }} />
            <div className="h-full bg-gold-500" style={{ width: `${100 - partGarcons}%` }} />
          </div>

          <div className="mt-4 flex items-center justify-between">
            <div className="flex flex-col gap-0.5">
              <span className="flex items-center gap-1.5 text-xs font-medium text-navy-400">
                <span className="h-2 w-2 rounded-full bg-navy-600" />
                {t('dashboard.boys')}
              </span>
              <span className="font-display text-2xl font-bold tabular-nums text-navy-800">{repartition_genre.garcons}</span>
            </div>
            <div className="flex flex-col items-end gap-0.5">
              <span className="flex items-center gap-1.5 text-xs font-medium text-navy-400">
                {t('dashboard.girls')}
                <span className="h-2 w-2 rounded-full bg-gold-500" />
              </span>
              <span className="font-display text-2xl font-bold tabular-nums text-gold-600">{repartition_genre.filles}</span>
            </div>
          </div>

          <dl className="mt-5 flex flex-col gap-2 border-t border-navy-50 pt-4 text-sm">
            <div className="flex justify-between">
              <dt className="text-navy-400">{t('dashboard.girls_rate')}</dt>
              <dd className="font-semibold tabular-nums text-navy-700">{indicateurs.taux_filles}%</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-navy-400">{t('dashboard.avg_students_per_class')}</dt>
              <dd className="font-semibold tabular-nums text-navy-700">{indicateurs.eleves_par_classe_moyenne}</dd>
            </div>
          </dl>
        </Card>
      </div>

      <Card>
        <h2 className="mb-4 font-display text-base font-bold tracking-tight text-navy-800">{t('dashboard.recent_activity')}</h2>
        <ul className="flex flex-col divide-y divide-navy-50">
          {activite_recente.map((a, i) => (
            <li key={i} className="flex items-center gap-3 py-3 text-sm">
              <span
                className={
                  a.type === 'eleve'
                    ? 'flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-navy-50 text-navy-600'
                    : 'flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-gold-50 text-gold-600'
                }
              >
                {a.type === 'eleve' ? <UserPlus className="h-4 w-4" /> : <BriefcaseBusiness className="h-4 w-4" />}
              </span>
              <span className="flex-1 text-navy-700">{a.libelle}</span>
              <span className="flex-none text-xs text-navy-400">{new Date(a.date).toLocaleString()}</span>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  )
}
