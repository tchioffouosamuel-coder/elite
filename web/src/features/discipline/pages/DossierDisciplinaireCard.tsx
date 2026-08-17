import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { ShieldAlert } from 'lucide-react'
import { fetchDossierDisciplinaire } from '@/features/discipline/api'
import type { Sanction, StatutSanction, TypeSanction } from '@/features/discipline/api'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Spinner } from '@/shared/ui/Feedback'

const TYPE_TONE: Record<TypeSanction, 'gold' | 'red' | 'neutral' | 'blue'> = {
  avertissement: 'blue',
  blame: 'gold',
  corvee: 'gold',
  exclusion_temporaire: 'red',
  exclusion_definitive: 'red',
  autre: 'neutral',
}

const STATUT_TONE: Record<StatutSanction, 'gold' | 'green' | 'neutral'> = {
  en_attente: 'gold',
  confirmee: 'green',
  annulee: 'neutral',
}

/**
 * Historique disciplinaire d'un élève, avec son statut d'exclusion résumé en
 * tête — le pendant du dossier disciplinaire de _smapp, calculé côté serveur
 * plutôt que reconstitué ici à partir de la seule liste des sanctions.
 */
export function DossierDisciplinaireCard({ eleveId }: { eleveId: number }) {
  const { t } = useTranslation()
  const { data: dossier, isLoading } = useQuery({
    queryKey: ['dossier-disciplinaire', eleveId],
    queryFn: () => fetchDossierDisciplinaire(eleveId),
  })

  return (
    <Card>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-sm font-bold uppercase tracking-wide text-navy-500">{t('discipline.dossier_disciplinaire')}</h2>
        {dossier?.est_exclu && (
          <Badge tone="red">
            <ShieldAlert className="h-3.5 w-3.5" />
            {t('discipline.est_exclu')}
          </Badge>
        )}
      </div>

      {isLoading || !dossier ? (
        <Spinner />
      ) : (
        <>
          <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('discipline.total_sanctions')}</p>
              <p className="text-lg font-bold tabular-nums text-navy-900">{dossier.total_sanctions}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{t('discipline.sanctions_en_cours')}</p>
              <p className="text-lg font-bold tabular-nums text-navy-900">{dossier.sanctions_en_cours}</p>
            </div>
          </div>

          {dossier.est_exclu && dossier.motif_exclusion && (
            <p className="mb-4 rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">{dossier.motif_exclusion}</p>
          )}

          {dossier.sanctions.length === 0 ? (
            <p className="text-sm text-navy-400">{t('discipline.aucune_sanction')}</p>
          ) : (
            <div className="flex flex-col divide-y divide-navy-50">
              {dossier.sanctions.map((s: Sanction) => (
                <div key={s.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                  <div className="flex min-w-0 items-center gap-2">
                    <Badge tone={TYPE_TONE[s.type]}>{t(`discipline.type_${s.type}`)}</Badge>
                    <span className="truncate text-sm text-navy-700">{s.motif}</span>
                  </div>
                  <div className="flex flex-none items-center gap-2">
                    <span className="text-xs text-navy-400">{new Date(s.date_sanction).toLocaleDateString('fr-FR')}</span>
                    <Badge tone={STATUT_TONE[s.statut]}>{t(`discipline.statut_${s.statut}`)}</Badge>
                  </div>
                </div>
              ))}
            </div>
          )}
        </>
      )}
    </Card>
  )
}
