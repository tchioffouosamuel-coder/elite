import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { BookOpen, Building2, Compass, Layers, ShieldAlert, UserCog, Users } from 'lucide-react'
import { fetchMesAttributions, type AttributionDetaillee } from '@/features/classes/api'
import type { CodeAttribution } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Card } from '@/shared/ui/Card'
import { EmptyState, ErrorState, Spinner } from '@/shared/ui/Feedback'
import { PageHeader } from '@/shared/ui/PageHeader'

/**
 * Ce que l'établissement a confié au compte connecté, responsabilité par
 * responsabilité — le pendant des entrées « Ma classe » et « Mon département »
 * de _smapp, réunies sur un seul écran parce qu'un même agent en cumule
 * souvent plusieurs (enseignant, professeur principal d'une classe et
 * surveillant général de trois autres).
 *
 * La liste des privilèges n'est pas décorative : elle répond à la question que
 * pose tout agent qui découvre une classe dans sa liste sans savoir ce qu'il a
 * le droit d'y faire.
 */
const ICONES: Record<CodeAttribution, typeof UserCog> = {
  professeur_principal: UserCog,
  surveillant_general: ShieldAlert,
  censeur: BookOpen,
  conseiller_orientation: Compass,
  chef_departement: Building2,
  animateur_niveau: Layers,
}

function CarteAttribution({ attribution }: { attribution: AttributionDetaillee }) {
  const { t } = useTranslation()
  const Icone = ICONES[attribution.code] ?? UserCog

  return (
    <Card className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-3">
        <span className="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-linear-to-br from-gold-50 to-gold-100 ring-1 ring-gold-100">
          <Icone className="h-4 w-4 text-gold-600" />
        </span>
        <h2 className="font-display text-base font-bold text-navy-900">{attribution.libelle}</h2>
        <Badge tone="gold">
          {t('attributions.nombre_classes', { count: attribution.classes.length })}
        </Badge>
      </div>

      {attribution.classes.length === 0 ? (
        <EmptyState label={t('attributions.aucune_classe')} />
      ) : (
        <ul className="flex flex-wrap gap-2">
          {attribution.classes.map((classe) => (
            <li key={classe.id}>
              <Link
                to={`/classes/${classe.id}`}
                className="flex items-center gap-2 rounded-xl border border-navy-100/70 bg-white/70 px-3 py-2 text-sm font-medium text-navy-700 transition-colors hover:border-gold-200 hover:text-gold-700"
              >
                <span>{classe.nom}</span>
                <span className="flex items-center gap-1 text-xs text-navy-400">
                  <Users className="h-3 w-3" />
                  {classe.effectif ?? 0}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}

      <div className="flex flex-col gap-1">
        <span className="text-[11px] font-semibold uppercase tracking-wide text-navy-400">
          {t('attributions.ce_que_cela_ouvre')}
        </span>
        <p className="text-xs leading-relaxed text-navy-500">
          {attribution.permissions.map((code) => t(`attributions.privilege.${code}`, code)).join(' · ')}
        </p>
      </div>
    </Card>
  )
}

export function MesAttributionsPage() {
  const { t } = useTranslation()
  const { data, isLoading, isError } = useQuery({
    queryKey: ['mes-attributions'],
    queryFn: fetchMesAttributions,
  })

  if (isLoading) return <Spinner />
  if (isError) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('attributions.title')} sousTitre={t('attributions.subtitle')} icon={UserCog} />

      {(data ?? []).length === 0 ? (
        <Card>
          <EmptyState label={t('attributions.aucune')} />
        </Card>
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {(data ?? []).map((attribution) => (
            <CarteAttribution key={attribution.code} attribution={attribution} />
          ))}
        </div>
      )}
    </div>
  )
}
