import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, Download, ImageOff, ScanFace } from 'lucide-react'
import {
  fetchClassesExamen,
  fetchDossierExamen,
  telechargerArchiveExamen,
  type CandidatExamen,
} from '@/features/identification/apiPhotosExamen'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card, StatCard } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
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

  // Une seule classe d'examen : la présélectionner évite un clic inutile.
  useEffect(() => {
    if (classeId === '' && classes?.length === 1) setClasseId(classes[0].id)
  }, [classes, classeId])

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
          <Select
            label={t('identification.classe_examen_label')}
            value={classeId}
            onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}
            className="max-w-sm"
          >
            <option value="">{t('identification.select_classe_placeholder')}</option>
            {classes.map((c) => (
              <option key={c.id} value={c.id}>
                {t('identification.classe_option_label', { nom: c.nom, code: c.code_examen, count: c.effectif })}
              </option>
            ))}
          </Select>

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
