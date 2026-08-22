import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Line, Bar } from 'react-chartjs-2'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Filler,
  Tooltip,
  Legend,
} from 'chart.js'
import { Activity, Clock, KeyRound, LogIn, TrendingUp, Users2 } from 'lucide-react'
import { fetchParentUsageStats, type VolumeDemandesAvecStatut } from '@/features/eleves/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card, StatCard } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Filler, Tooltip, Legend)

// Couleurs de la charte, jamais recyclées d'une série à l'autre : la même
// couleur désigne toujours la même chose sur cette page. Vert et rouge sont
// réservés au statut (validée / rejetée, cf. `BarreRepartition`) — le
// graphique des volumes utilise donc un jeu distinct (ambre, framboise) pour
// ne pas laisser entendre qu'une "Justification" ou une "Observation" serait
// elle-même validée ou rejetée. Vérifié avec le validateur de palette du
// skill dataviz (séparation daltonisme + contraste), les deux jeux passent.
const NAVY = '#6a3394'
const GOLD = '#1985cc'
const GREEN = '#26733d'
const RED = '#ba2e2c'
const AMBRE = '#d97706'
const FRAMBOISE = '#e11d48'

const OPTIONS_JOURS = [
  { valeur: 7, libelle: '7 derniers jours' },
  { valeur: 30, libelle: '30 derniers jours' },
  { valeur: 90, libelle: '90 derniers jours' },
] as const

function dateCourte(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
}

const OPTIONS_LIGNE = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index' as const, intersect: false },
  plugins: { legend: { display: true, position: 'top' as const, labels: { boxWidth: 10, usePointStyle: true } } },
  scales: {
    y: { beginAtZero: true, ticks: { precision: 0 } },
    x: { grid: { display: false } },
  },
}

/** Répartition en_attente/validée/rejetée d'un type de demande, en barre empilée horizontale. */
function BarreRepartition({ titre, donnees }: { titre: string; donnees: VolumeDemandesAvecStatut }) {
  const { en_attente, validee, rejetee } = donnees.repartition
  const total = Math.max(1, en_attente + validee + rejetee)

  return (
    <div className="flex flex-col gap-1.5">
      <div className="flex items-center justify-between text-xs">
        <span className="font-semibold text-navy-700">{titre}</span>
        <span className="text-navy-400">{donnees.total} au total</span>
      </div>
      <div className="flex h-3 w-full overflow-hidden rounded-full bg-navy-50">
        {en_attente > 0 && <div style={{ width: `${(en_attente / total) * 100}%`, backgroundColor: GOLD }} />}
        {validee > 0 && (
          <div style={{ width: `${(validee / total) * 100}%`, backgroundColor: GREEN }} className="ml-0.5" />
        )}
        {rejetee > 0 && <div style={{ width: `${(rejetee / total) * 100}%`, backgroundColor: RED }} className="ml-0.5" />}
      </div>
      <div className="flex flex-wrap gap-3 text-[11px] text-navy-500">
        <span className="flex items-center gap-1">
          <span className="h-2 w-2 rounded-full" style={{ backgroundColor: GOLD }} />
          En attente ({en_attente})
        </span>
        <span className="flex items-center gap-1">
          <span className="h-2 w-2 rounded-full" style={{ backgroundColor: GREEN }} />
          Validées ({validee})
        </span>
        <span className="flex items-center gap-1">
          <span className="h-2 w-2 rounded-full" style={{ backgroundColor: RED }} />
          Rejetées ({rejetee})
        </span>
      </div>
    </div>
  )
}

/**
 * Suivi d'adoption et d'usage du portail parent : comptes ouverts,
 * connexions, volumes de démarches déposées et délai de traitement — pour
 * juger en continu si le portail remplace effectivement les démarches
 * papier plutôt que de simplement exister à côté d'elles.
 */
export function AdminParentStatsPage() {
  const [jours, setJours] = useState<7 | 30 | 90>(30)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['parent-usage-stats', jours],
    queryFn: () => fetchParentUsageStats(jours),
  })

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  const labelsActivite = data.activite.serie_quotidienne.map((p) => dateCourte(p.date))

  const dataActivite = {
    labels: labelsActivite,
    datasets: [
      {
        label: 'Connexions parent',
        data: data.activite.serie_quotidienne.map((p) => p.total),
        borderColor: NAVY,
        backgroundColor: `${NAVY}1a`,
        fill: true,
        tension: 0.3,
        borderWidth: 2,
        pointRadius: 2,
      },
      {
        label: 'Comptes parent ouverts',
        data: data.adoption.comptes_ouverts_serie.map((p) => p.total),
        borderColor: GOLD,
        backgroundColor: `${GOLD}1a`,
        fill: true,
        tension: 0.3,
        borderWidth: 2,
        pointRadius: 2,
      },
    ],
  }

  const dataVolumes = {
    labels: data.volumes.preinscriptions.serie.map((p) => dateCourte(p.date)),
    datasets: [
      { label: 'Préinscriptions', data: data.volumes.preinscriptions.serie.map((p) => p.total), backgroundColor: NAVY },
      { label: 'Modifications', data: data.volumes.modifications.serie.map((p) => p.total), backgroundColor: GOLD },
      { label: 'Justifications', data: data.volumes.justifications.serie.map((p) => p.total), backgroundColor: AMBRE },
      { label: 'Observations', data: data.volumes.observations.serie.map((p) => p.total), backgroundColor: FRAMBOISE },
    ],
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Statistiques du portail parent"
        sousTitre={`Du ${new Date(data.periode.debut).toLocaleDateString('fr-FR')} au ${new Date(data.periode.fin).toLocaleDateString('fr-FR')}.`}
        icon={TrendingUp}
        actions={
          <Select value={jours} onChange={(e) => setJours(Number(e.target.value) as 7 | 30 | 90)} className="w-48">
            {OPTIONS_JOURS.map((o) => (
              <option key={o.valeur} value={o.valeur}>
                {o.libelle}
              </option>
            ))}
          </Select>
        }
      />

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
        <StatCard
          label="Taux d'adoption"
          value={`${data.adoption.taux_adoption} %`}
          icon={Users2}
          accent="navy"
          hint={`${data.adoption.comptes_parent_total} / ${data.adoption.tuteurs_total} tuteurs`}
        />
        <StatCard
          label="Parents actifs (7 j)"
          value={data.activite.parents_actifs_7j}
          icon={Activity}
          accent="green"
          hint={`${data.activite.parents_actifs_distincts} sur la période`}
        />
        <StatCard label="Connexions" value={data.activite.connexions_totales} icon={LogIn} accent="gold" hint="Sur la période" />
        <StatCard
          label="Délai — préinscriptions"
          value={data.efficience.delai_moyen_preinscriptions_heures != null ? `${data.efficience.delai_moyen_preinscriptions_heures} h` : '—'}
          icon={Clock}
          accent="navy"
          hint="Dépôt → traitement"
        />
        <StatCard
          label="Délai — modifications"
          value={data.efficience.delai_moyen_modifications_heures != null ? `${data.efficience.delai_moyen_modifications_heures} h` : '—'}
          icon={Clock}
          accent="navy"
          hint="Dépôt → traitement"
        />
        <StatCard label="Comptes parent ouverts" value={data.adoption.comptes_parent_total} icon={KeyRound} accent="gold" />
      </div>

      <Card>
        <h2 className="mb-3 font-display text-base font-bold text-navy-900">Activité quotidienne</h2>
        <div className="h-72">
          <Line data={dataActivite} options={OPTIONS_LIGNE} />
        </div>
      </Card>

      <Card>
        <h2 className="mb-3 font-display text-base font-bold text-navy-900">Démarches déposées par les parents</h2>
        <div className="h-72">
          <Bar
            data={dataVolumes}
            options={{
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: true, position: 'top' as const, labels: { boxWidth: 10, usePointStyle: true } } },
              scales: { y: { beginAtZero: true, stacked: false, ticks: { precision: 0 } }, x: { grid: { display: false } } },
            }}
          />
        </div>
      </Card>

      <Card className="flex flex-col gap-4">
        <h2 className="font-display text-base font-bold text-navy-900">Traitement des demandes</h2>
        <BarreRepartition titre="Préinscriptions" donnees={data.volumes.preinscriptions} />
        <BarreRepartition titre="Modifications de fiches" donnees={data.volumes.modifications} />
      </Card>
    </div>
  )
}
