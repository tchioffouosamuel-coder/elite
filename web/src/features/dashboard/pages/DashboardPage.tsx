import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  UserRound,
  Users,
  GraduationCap,
  School,
  BookOpen,
  ListChecks,
  GitBranch,
  RadioTower,
  RefreshCw,
  Clock,
  CalendarClock,
  AlarmClockOff,
  UserX,
  TrendingUp,
  Eye,
} from 'lucide-react'
import { fetchDashboardStats, fetchPilotage, type CreneauPilotage, type ActiviteLog } from '@/features/dashboard/api'
import { LigneActivite } from '@/features/dashboard/pages/LigneActivite'
import { StatCard, Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { useAuthStore } from '@/shared/store/authStore'

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

function ActiviteRecente({ activite }: { activite: ActiviteLog[] }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  return (
    <Card>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="font-display text-base font-bold tracking-tight text-navy-800">{t('dashboard.recent_activity')}</h2>
        <button
          type="button"
          onClick={() => navigate('/journal-activite')}
          className="text-xs font-semibold text-navy-500 hover:text-navy-800"
        >
          {t('dashboard.see_more')}
        </button>
      </div>
      <ul className="flex flex-col divide-y divide-navy-50">
        {activite.map((a, i) => (
          <LigneActivite key={i} a={a} />
        ))}
      </ul>
    </Card>
  )
}

/** Titulaire de primaire/maternelle : le tableau de bord se limite à sa classe. */
function TableauClasse({ data }: { data: Extract<import('@/features/dashboard/api').DashboardStats, { scope: 'classe' }> }) {
  const { t } = useTranslation()
  const isSuperAdmin = useAuthStore((s) => s.user?.is_super_admin ?? false)
  const typeEcole = useAuthStore((s) => s.activeSchool()?.type)
  const { classe, effectifs, repartition_genre, indicateurs, activite_recente, annee_scolaire_active } = data
  const totalGenre = Math.max(1, repartition_genre.garcons + repartition_genre.filles)
  const partGarcons = Math.round((repartition_genre.garcons / totalGenre) * 100)
  // Le décompte reste celui des matières installées sous les compétences au
  // primaire/maternelle (cf. ClasseMatiereController) : le nombre est juste,
  // seul le mot doit parler le langage de l'enseignant qui le lit.
  const libelleMatieres = typeEcole === 'secondaire' ? t('dashboard.classSubjects') : t('dashboard.competenciesCount')

  return (
    <div className="flex flex-col gap-6">
      <EnTete
        titre={classe.nom}
        sousTitre={annee_scolaire_active ? `${t('dashboard.active_year')} : ${annee_scolaire_active}` : undefined}
      />

      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <StatCard label={t('dashboard.classes')} value={effectifs.classes} icon={School} accent="navy" />
        <StatCard label={libelleMatieres} value={effectifs.matieres} icon={BookOpen} accent="gold" />
        <StatCard
          label={t('dashboard.fillRate')}
          value={indicateurs.taux_remplissage_notes === null ? '—' : `${indicateurs.taux_remplissage_notes}%`}
          icon={ListChecks}
          accent="green"
        />
        <StatCard
          label={t('dashboard.progressRate')}
          value={indicateurs.taux_progression === null ? '—' : `${indicateurs.taux_progression}%`}
          icon={GitBranch}
          accent="navy"
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Card>
          <h2 className="mb-5 font-display text-base font-bold tracking-tight text-navy-800">{t('dashboard.gender_distribution')}</h2>

          <div className="flex h-2.5 overflow-hidden rounded-full bg-cream-100">
            <div className="h-full bg-navy-600" style={{ width: `${partGarcons}%` }} />
            <div className="h-full bg-gold-500" style={{ width: `${100 - partGarcons}%` }} />
          </div>

          <dl className="mt-5 flex flex-col gap-2 border-t border-navy-50 pt-4 text-sm">
            <div className="flex justify-between">
              <dt className="text-navy-400">{t('dashboard.students')}</dt>
              <dd className="font-semibold tabular-nums text-navy-700">{effectifs.eleves}</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-navy-400">{t('dashboard.boys')}</dt>
              <dd className="font-semibold tabular-nums text-navy-700">{repartition_genre.garcons}</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-navy-400">{t('dashboard.girls')}</dt>
              <dd className="font-semibold tabular-nums text-navy-700">{repartition_genre.filles}</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-navy-400">{t('dashboard.girls_rate')}</dt>
              <dd className="font-semibold tabular-nums text-navy-700">{indicateurs.taux_filles}%</dd>
            </div>
          </dl>
        </Card>
      </div>

      {isSuperAdmin && <ActiviteRecente activite={activite_recente} />}
    </div>
  )
}

/** Une ligne « HH:MM–HH:MM · Classe · Matière · Enseignant », commune aux trois listes de créneaux. */
function LigneCreneau({ creneau, accentAppel, onDetail }: { creneau: CreneauPilotage; accentAppel?: boolean; onDetail: (c: CreneauPilotage) => void }) {
  const { t } = useTranslation()
  return (
    <li className="flex items-center gap-3 py-2.5 text-sm">
      <span className="w-24 flex-none tabular-nums text-navy-500">
        {creneau.heure_debut}–{creneau.heure_fin}
      </span>
      <span className="flex-1 truncate">
        <span className="font-semibold text-navy-800">{creneau.classe}</span>
        {creneau.matiere && <span className="text-navy-400"> · {creneau.matiere}</span>}
        {creneau.enseignant && <span className="text-navy-400"> · {creneau.enseignant}</span>}
      </span>
      {accentAppel && (
        <Badge tone={creneau.appel_fait ? 'green' : 'gold'}>
          {creneau.appel_fait ? t('dashboard.call_done') : t('dashboard.call_pending')}
        </Badge>
      )}
      <button
        type="button"
        onClick={() => onDetail(creneau)}
        aria-label={t('dashboard.creneau_view_details') ?? undefined}
        title={t('dashboard.creneau_view_details') ?? undefined}
        className="flex-none rounded-full p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
      >
        <Eye className="h-4 w-4" />
      </button>
    </li>
  )
}

function ListeCreneaux({ titre, icon: Icon, creneaux, vide, accentAppel, onDetail }: {
  titre: string
  icon: typeof Clock
  creneaux: CreneauPilotage[]
  vide: string
  accentAppel?: boolean
  onDetail: (c: CreneauPilotage) => void
}) {
  return (
    <Card>
      <h3 className="mb-2 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <Icon className="h-4 w-4" />
        {titre}
        <span className="ml-auto rounded-full bg-cream-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-navy-500">
          {creneaux.length}
        </span>
      </h3>
      {creneaux.length === 0 ? (
        <p className="py-4 text-sm text-navy-300">{vide}</p>
      ) : (
        <ul className="flex flex-col divide-y divide-navy-50">
          {creneaux.map((c) => (
            <LigneCreneau key={c.emploi_du_temps_id} creneau={c} accentAppel={accentAppel} onDetail={onDetail} />
          ))}
        </ul>
      )}
    </Card>
  )
}

/** Détail non tronqué d'un créneau du pilotage, affiché en modale au clic sur l'œil de la ligne. */
function CreneauDetailModal({ creneau, onClose }: { creneau: CreneauPilotage; onClose: () => void }) {
  const { t } = useTranslation()
  const champs: { label: string; valeur: string | null }[] = [
    { label: t('dashboard.creneau_horaire'), valeur: `${creneau.heure_debut}–${creneau.heure_fin}` },
    { label: t('dashboard.creneau_classe'), valeur: creneau.classe },
    { label: t('dashboard.creneau_ecole'), valeur: creneau.ecole },
    { label: t('dashboard.creneau_matiere'), valeur: creneau.matiere },
    { label: t('dashboard.creneau_enseignant'), valeur: creneau.enseignant },
    { label: t('dashboard.creneau_salle'), valeur: creneau.salle },
  ]

  return (
    <Modal title={t('dashboard.creneau_details')} onClose={onClose}>
      <dl className="flex flex-col gap-3 text-sm">
        {champs.map(({ label, valeur }) => (
          <div key={label} className="flex justify-between gap-4">
            <dt className="text-navy-400">{label}</dt>
            <dd className="text-right font-semibold text-navy-800">{valeur ?? '—'}</dd>
          </div>
        ))}
        <div className="flex justify-between gap-4">
          <dt className="text-navy-400">{t('dashboard.creneau_statut_appel')}</dt>
          <dd>
            <Badge tone={creneau.appel_fait ? 'green' : 'gold'}>
              {creneau.appel_fait ? t('dashboard.call_done') : t('dashboard.call_pending')}
            </Badge>
          </dd>
        </div>
      </dl>
    </Modal>
  )
}

/**
 * Pilotage en temps réel : chargé à la demande (pas au premier rendu, l'appel
 * parcourt tout le programme de l'établissement) puis rafraîchi toutes les
 * 60 s tant que le panneau reste ouvert, avec un bouton pour forcer une
 * actualisation immédiate.
 */
function PilotagePanel() {
  const { t } = useTranslation()
  const [ouvert, setOuvert] = useState(false)
  const [creneauDetail, setCreneauDetail] = useState<CreneauPilotage | null>(null)

  const { data, isLoading, isError, isFetching, refetch } = useQuery({
    queryKey: ['dashboard', 'pilotage'],
    queryFn: fetchPilotage,
    enabled: ouvert,
    refetchInterval: ouvert ? 60_000 : false,
  })

  if (!ouvert) {
    return (
      <Card className="flex flex-col items-center gap-3 py-8 text-center">
        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-navy-50 text-navy-500">
          <RadioTower className="h-5 w-5" />
        </span>
        <div>
          <h2 className="font-display text-base font-bold tracking-tight text-navy-800">{t('dashboard.pilotage_title')}</h2>
          <p className="mt-1 text-sm text-navy-400">{t('dashboard.pilotage_subtitle')}</p>
        </div>
        <Button variant="secondary" onClick={() => setOuvert(true)}>
          <RadioTower className="h-4 w-4" />
          {t('dashboard.pilotage_show')}
        </Button>
      </Card>
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="font-display text-base font-bold tracking-tight text-navy-800">{t('dashboard.pilotage_title')}</h2>
          {data && (
            <p className="mt-0.5 text-xs text-navy-400">
              {t('dashboard.pilotage_generated_at', { heure: new Date(data.genere_le).toLocaleTimeString() })}
            </p>
          )}
        </div>
        <Button variant="secondary" onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw className={`h-4 w-4 ${isFetching ? 'animate-spin' : ''}`} />
          {t('dashboard.pilotage_refresh')}
        </Button>
      </div>

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState message={t('dashboard.pilotage_error')} />
      ) : (
        <>
          <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <ListeCreneaux
              titre={t('dashboard.ongoing_lessons')}
              icon={Clock}
              creneaux={data.cours_en_cours}
              vide={t('dashboard.no_ongoing_lessons')}
              onDetail={setCreneauDetail}
            />
            <ListeCreneaux
              titre={t('dashboard.upcoming_lessons')}
              icon={CalendarClock}
              creneaux={data.cours_a_venir}
              vide={t('dashboard.no_upcoming_lessons')}
              onDetail={setCreneauDetail}
            />
            <ListeCreneaux
              titre={t('dashboard.overdue_calls')}
              icon={AlarmClockOff}
              creneaux={data.appels_en_retard}
              vide={t('dashboard.no_overdue_calls')}
              accentAppel
              onDetail={setCreneauDetail}
            />
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Card>
              <h3 className="mb-2 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
                <UserX className="h-4 w-4" />
                {t('dashboard.classes_without_teacher')}
                <span className="ml-auto rounded-full bg-cream-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-navy-500">
                  {data.classes_sans_enseignant.length}
                </span>
              </h3>
              {data.classes_sans_enseignant.length === 0 ? (
                <p className="py-4 text-sm text-navy-300">{t('dashboard.no_classes_without_teacher')}</p>
              ) : (
                <ul className="flex flex-col divide-y divide-navy-50">
                  {data.classes_sans_enseignant.map((c, i) => (
                    <li key={i} className="flex items-center gap-2 py-2.5 text-sm">
                      <span className="font-semibold text-navy-800">{c.classe}</span>
                      {c.matiere && <span className="text-navy-400">· {c.matiere}</span>}
                      <span className="ml-auto text-xs text-navy-400">{c.ecole}</span>
                    </li>
                  ))}
                </ul>
              )}
            </Card>

            <Card>
              <h3 className="mb-2 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
                <TrendingUp className="h-4 w-4" />
                {t('dashboard.global_coverage')}
              </h3>
              <div className="flex items-end gap-3">
                <span className="font-display text-3xl font-bold tabular-nums text-navy-800">{data.couverture.taux}%</span>
                <span className="mb-1 text-xs text-navy-400">
                  {t('dashboard.lessons_covered', { traitees: data.couverture.traitees, lecons: data.couverture.lecons })}
                </span>
              </div>
              <div className="mt-3 h-2.5 rounded-full bg-cream-100">
                <div
                  className="h-2.5 rounded-full bg-gradient-to-r from-green-500 to-green-300 transition-all"
                  style={{ width: `${Math.min(data.couverture.taux, 100)}%` }}
                />
              </div>

              {data.couverture.classes_en_retard.length > 0 && (
                <div className="mt-4 border-t border-navy-50 pt-3">
                  <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-navy-400">{t('dashboard.lagging_classes')}</p>
                  <ul className="flex flex-col gap-1.5">
                    {data.couverture.classes_en_retard.map((c, i) => (
                      <li key={i} className="flex items-center justify-between text-sm">
                        <span className="text-navy-700">{c.classe}</span>
                        <span className="font-semibold tabular-nums text-navy-500">{c.taux}%</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </Card>
          </div>
        </>
      )}

      {creneauDetail && <CreneauDetailModal creneau={creneauDetail} onClose={() => setCreneauDetail(null)} />}
    </div>
  )
}

function TableauEcole({ data }: { data: Extract<import('@/features/dashboard/api').DashboardStats, { scope: 'ecole' }> }) {
  const { t } = useTranslation()
  const isSuperAdmin = useAuthStore((s) => s.user?.is_super_admin ?? false)
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

      <PilotagePanel />

      {isSuperAdmin && <ActiviteRecente activite={activite_recente} />}
    </div>
  )
}
