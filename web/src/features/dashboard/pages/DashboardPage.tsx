import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { UserRound, Users, GraduationCap, School, UserPlus, BriefcaseBusiness, BookOpen } from 'lucide-react'
import { fetchDashboardStats } from '@/features/dashboard/api'
import { StatCard, Card } from '@/shared/ui/Card'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

export function DashboardPage() {
  const { data, isLoading, isError } = useQuery({ queryKey: ['dashboard'], queryFn: fetchDashboardStats })

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  return data.scope === 'classe' ? <TableauClasse data={data} /> : <TableauEcole data={data} />
}

function EnTete({ titre, sousTitre }: { titre: string; sousTitre?: string | null }) {
  return (
    <div>
      <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{titre}</h1>
      {sousTitre && <p className="mt-1 text-sm text-navy-400">{sousTitre}</p>}
    </div>
  )
}

function ActiviteRecente({ activite }: { activite: { type: string; libelle: string; date: string }[] }) {
  const { t } = useTranslation()
  return (
    <Card>
      <h2 className="mb-4 font-display text-base font-bold tracking-tight text-navy-800">{t('dashboard.recent_activity')}</h2>
      <ul className="flex flex-col divide-y divide-navy-50">
        {activite.map((a, i) => (
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
  )
}

/** Titulaire de primaire/maternelle : le tableau de bord se limite à sa classe. */
function TableauClasse({ data }: { data: Extract<import('@/features/dashboard/api').DashboardStats, { scope: 'classe' }> }) {
  const { t } = useTranslation()
  const { classe, effectifs, repartition_genre, indicateurs, activite_recente, annee_scolaire_active } = data
  const totalGenre = Math.max(1, repartition_genre.garcons + repartition_genre.filles)
  const partGarcons = Math.round((repartition_genre.garcons / totalGenre) * 100)

  return (
    <div className="flex flex-col gap-6">
      <EnTete
        titre={classe.nom}
        sousTitre={annee_scolaire_active ? `${t('dashboard.active_year')} : ${annee_scolaire_active}` : undefined}
      />

      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <StatCard label={t('dashboard.students')} value={effectifs.eleves} icon={UserRound} accent="navy" />
        <StatCard label={t('dashboard.classSubjects')} value={effectifs.matieres} icon={BookOpen} accent="gold" />
        <StatCard label={t('dashboard.girls')} value={repartition_genre.filles} icon={Users} accent="green" />
        <StatCard label={t('dashboard.boys')} value={repartition_genre.garcons} icon={GraduationCap} accent="navy" />
      </div>

      <Card className="max-w-md">
        <h2 className="mb-5 font-display text-base font-bold tracking-tight text-navy-800">{t('dashboard.gender_distribution')}</h2>

        <div className="flex h-2.5 overflow-hidden rounded-full bg-cream-100">
          <div className="h-full bg-navy-600" style={{ width: `${partGarcons}%` }} />
          <div className="h-full bg-gold-500" style={{ width: `${100 - partGarcons}%` }} />
        </div>

        <dl className="mt-5 flex flex-col gap-2 border-t border-navy-50 pt-4 text-sm">
          <div className="flex justify-between">
            <dt className="text-navy-400">{t('dashboard.girls_rate')}</dt>
            <dd className="font-semibold tabular-nums text-navy-700">{indicateurs.taux_filles}%</dd>
          </div>
        </dl>
      </Card>

      <ActiviteRecente activite={activite_recente} />
    </div>
  )
}

function TableauEcole({ data }: { data: Extract<import('@/features/dashboard/api').DashboardStats, { scope: 'ecole' }> }) {
  const { t } = useTranslation()
  const { effectifs, repartition_genre, top_classes, indicateurs, activite_recente, annee_scolaire_active } = data
  const maxClasseEffectif = Math.max(1, ...top_classes.map((c) => c.effectif))
  const totalGenre = Math.max(1, repartition_genre.garcons + repartition_genre.filles)
  const partGarcons = Math.round((repartition_genre.garcons / totalGenre) * 100)

  return (
    <div className="flex flex-col gap-6">
      <EnTete
        titre={t('dashboard.title')}
        sousTitre={annee_scolaire_active ? `${t('dashboard.active_year')} : ${annee_scolaire_active}` : undefined}
      />

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

      <ActiviteRecente activite={activite_recente} />
    </div>
  )
}
