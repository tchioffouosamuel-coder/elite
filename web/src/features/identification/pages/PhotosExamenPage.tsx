import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, Download, Eye, ImageOff, ScanFace } from 'lucide-react'
import {
  fetchClassesExamen,
  fetchDossierExamen,
  telechargerArchiveExamen,
  type CandidatExamen,
} from '@/features/identification/apiPhotosExamen'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card, StatCard } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { confirmer, erreur, info, succes } from '@/shared/lib/alertes'
import { estSecondaire } from '@/shared/lib/ecole'
import type { ApiError } from '@/shared/types/api'

/** Vignette d'un candidat, à l'image de ce que recevra l'organisme. */
function Vignette({ candidat }: { candidat: CandidatExamen }) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col items-center gap-1.5">
      <div className="relative aspect-4/5 w-full overflow-hidden rounded-xl border border-navy-100 bg-cream-50 shadow-soft">
        {candidat.photo_prete && candidat.photo_url ? (
          <img src={candidat.photo_url} alt={candidat.nom_complet} className="h-full w-full object-cover" />
        ) : (
          <div className="flex h-full w-full flex-col items-center justify-center gap-1 text-navy-300">
            <ImageOff className="h-6 w-6" />
            <span className="text-[10px] font-semibold uppercase">{t('identification.no_photo')}</span>
          </div>
        )}
        {!candidat.photo_prete && <span className="absolute inset-0 ring-2 ring-inset ring-red-300" />}
      </div>
      <span className="w-full truncate text-center text-xs font-semibold text-navy-800" title={candidat.nom_complet}>
        {candidat.nom_complet}
      </span>
      <span className="font-mono text-[10px] text-navy-400">{candidat.matricule ?? '—'}</span>
    </div>
  )
}

export function PhotosExamenPage() {
  const { t } = useTranslation()
  const [classeId, setClasseId] = useState<number | ''>('')
  const [enCours, setEnCours] = useState(false)

  const { data: classes, isLoading: chargementClasses } = useQuery({
    queryKey: ['classes-examen'],
    queryFn: fetchClassesExamen,
  })

  const { data: dossier, isLoading } = useQuery({
    queryKey: ['dossier-examen', classeId],
    queryFn: () => fetchDossierExamen(Number(classeId)),
    enabled: classeId !== '',
  })

  const telecharger = async () => {
    if (!dossier) return

    if (dossier.manquants > 0) {
      const suite = await confirmer({
        titre: t('identification.confirm_missing_title', { count: dossier.manquants }),
        message: t('identification.confirm_missing_message'),
        action: t('identification.confirm_missing_action'),
        destructif: false,
      })
      if (!suite) return
    }

    setEnCours(true)
    try {
      const { traites, ignores } = await telechargerArchiveExamen(Number(classeId))
      succes(t('identification.archive_generated', { count: traites }))
      if (ignores > 0) info(t('identification.photos_skipped', { count: ignores }))
    } catch (e) {
      erreur((e as ApiError).message ?? t('identification.archive_error'))
    } finally {
      setEnCours(false)
    }
  }

  if (chargementClasses) return <Spinner />

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={estSecondaire() ? t('nav.photosExamen') : t('nav.photosExamenPrimaire')}
        sousTitre={t('identification.photos_examen_subtitle')}
        icon={ScanFace}
        actions={
          dossier && (
            <Button onClick={telecharger} disabled={enCours || dossier.prets === 0}>
              <Download className="h-4 w-4" />
              {enCours ? t('identification.generating') : t('identification.download_archive')}
            </Button>
          )
        }
      />

      {!classes?.length ? (
        <Card>
          <EmptyState label={t('identification.empty_classes_examen')} />
        </Card>
      ) : (
        <>
          <Card className="overflow-hidden p-0">
            <div className="overflow-x-auto">
              <table className="w-full min-w-[760px] border-collapse text-sm">
                <thead className="bg-linear-to-b from-cream-100 to-cream-100/80 text-left text-xs font-semibold uppercase tracking-wide text-navy-500">
                  <tr>
                    <th className="px-5 py-3.5">{t('identification.class_name')}</th>
                    <th className="px-5 py-3.5">{t('identification.class_enrolled')}</th>
                    <th className="px-5 py-3.5">{t('identification.class_photos')}</th>
                    <th className="w-72 px-5 py-3.5">{t('identification.class_progress')}</th>
                    <th className="px-5 py-3.5 text-right">{t('common.actions')}</th>
                  </tr>
                </thead>
                <tbody>
                  {classes.map((classe) => {
                    const pourcentage = classe.effectif > 0
                      ? Math.min(100, Math.round((classe.photos / classe.effectif) * 100))
                      : 0
                    const selectionnee = classeId === classe.id

                    return (
                      <tr
                        key={classe.id}
                        className={`border-t border-navy-50 transition-colors ${selectionnee ? 'bg-gold-50/60' : 'even:bg-cream-50/40 hover:bg-gold-50/40'}`}
                      >
                        <td className="px-5 py-4">
                          <div className="font-semibold text-navy-900">{classe.nom}</div>
                          <div className="mt-0.5 text-xs text-navy-400">{classe.code_examen}</div>
                        </td>
                        <td className="px-5 py-4 font-semibold tabular-nums text-navy-800">{classe.effectif}</td>
                        <td className="px-5 py-4 tabular-nums text-navy-700">
                          <span className="font-semibold">{classe.photos}</span>
                          <span className="text-navy-400"> / {classe.effectif}</span>
                        </td>
                        <td className="px-5 py-4">
                          <div className="flex items-center gap-3">
                            <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-navy-100">
                              <div
                                className={`h-full rounded-full transition-all ${pourcentage === 100 ? 'bg-green-500' : 'bg-gold-500'}`}
                                style={{ width: `${pourcentage}%` }}
                              />
                            </div>
                            <span className="w-10 text-right text-xs font-semibold tabular-nums text-navy-600">{pourcentage}%</span>
                          </div>
                        </td>
                        <td className="px-5 py-4 text-right">
                          <Button variant="secondary" onClick={() => setClasseId(classe.id)}>
                            <Eye className="h-4 w-4" />
                            {t('identification.view_details')}
                          </Button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </Card>

          {classeId === '' ? (
            <Card>
              <EmptyState label={t('identification.empty_select_classe_candidats')} />
            </Card>
          ) : isLoading || !dossier ? (
            <Spinner />
          ) : (
            <>
              <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label={t('identification.stat_candidats')} value={dossier.candidats.length} accent="navy" />
                <StatCard label={t('identification.stat_photos_pretes')} value={dossier.prets} icon={CheckCircle2} accent="green" />
                <StatCard label={t('identification.no_photo')} value={dossier.manquants} icon={ImageOff} accent="red" />
                <StatCard
                  label={t('identification.stat_code_examen')}
                  value={dossier.code_examen ?? '—'}
                  accent="gold"
                  hint={dossier.centre ? t('identification.hint_centre', { centre: dossier.centre }) : t('identification.hint_centre_non_renseigne')}
                />
              </div>

              {!dossier.centre && (
                <Card className="flex items-start gap-3 border-gold-100 bg-gold-50/50">
                  <ImageOff className="mt-0.5 h-4 w-4 flex-none text-gold-600" />
                  <p className="text-sm text-navy-600">
                    {t('identification.centre_missing_before')}
                    <strong className="text-navy-800">{t('identification.centre_missing_bold')}</strong>
                    {t('identification.centre_missing_after')}
                  </p>
                </Card>
              )}

              <Card className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <h2 className="font-display text-base font-bold text-navy-900">{t('identification.stat_candidats')}</h2>
                  {dossier.manquants > 0 && (
                    <Badge tone="red">{t('identification.badge_sans_photo_count', { count: dossier.manquants })}</Badge>
                  )}
                </div>
                {/* Chaque photo est recomposée à la génération : fond blanc,
                    date, nom et code d'examen. La vignette montre la source. */}
                <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
                  {dossier.candidats.map((c) => (
                    <Vignette key={c.eleve_id} candidat={c} />
                  ))}
                </div>
              </Card>
            </>
          )}
        </>
      )}
    </div>
  )
}
