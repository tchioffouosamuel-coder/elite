import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useParams } from 'react-router-dom'
import { clsx } from 'clsx'
import { ArrowLeft, Check, ChevronDown, ClipboardCheck, Lock } from 'lucide-react'
import { enregistrerAppel, fetchAppel, MOTIFS, type LigneAppel, type MotifAbsence } from '@/features/emploiDuTemps/api'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { ErrorState, Spinner } from '@/shared/ui/Feedback'
import { PageHeader } from '@/shared/ui/PageHeader'
import { erreur, succes } from '@/shared/lib/alertes'

export function AppelPage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const seanceId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data, isLoading, isError } = useQuery({
    queryKey: ['appel', seanceId],
    queryFn: () => fetchAppel(seanceId),
    enabled: Number.isFinite(seanceId),
  })

  const [lignes, setLignes] = useState<LigneAppel[]>([])

  useEffect(() => {
    if (data) setLignes(data.lignes)
  }, [data])

  const enregistrement = useMutation({
    mutationFn: () =>
      enregistrerAppel(
        seanceId,
        lignes.map((l) => ({ eleve_id: l.eleve_id, statut: l.statut, motif: l.motif, remarque: l.remarque })),
      ),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['seances', data?.seance.classe_id] })
      queryClient.invalidateQueries({ queryKey: ['appel', seanceId] })
      succes(t('emploiDuTemps.appel_enregistre', { count: res.enregistres }))
      navigate('/seances')
    },
    onError: (e: { message?: string }) => erreur(e.message ?? t('emploiDuTemps.appel_erreur')),
  })

  const marquerPresent = (eleveId: number) =>
    setLignes((actuel) => actuel.map((l) => (l.eleve_id === eleveId ? { ...l, statut: 'present', motif: null } : l)))

  const marquerAbsent = (eleveId: number, motif: MotifAbsence) =>
    setLignes((actuel) => actuel.map((l) => (l.eleve_id === eleveId ? { ...l, statut: 'absent', motif } : l)))

  if (isLoading || !data) return <Spinner />
  if (isError) return <ErrorState />

  const absents = lignes.filter((l) => l.statut === 'absent').length
  const verrouille = data.verrouille
  const heureLimite = data.modifiable_jusqua
    ? new Date(data.modifiable_jusqua).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    : null

  return (
    <div className="flex flex-col gap-5">
      <div>
        <button
          onClick={() => navigate('/seances')}
          className="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
        >
          <ArrowLeft className="h-4 w-4" />
          {t('emploiDuTemps.retour_seances')}
        </button>
        <PageHeader
          titre={t('emploiDuTemps.appel_titre', { matiere: data.seance.matiere ?? '' })}
          sousTitre={`${new Date(data.seance.date_seance).toLocaleDateString('fr-FR')} · ${data.seance.heure_debut}–${data.seance.heure_fin}`}
          icon={ClipboardCheck}
        />
      </div>

      <Card className="p-4">
        {verrouille && (
          <p className="mb-3 flex items-center gap-2 rounded-xl bg-navy-50 px-3.5 py-2.5 text-sm text-navy-700">
            <Lock className="h-4 w-4 shrink-0" />
            {t('emploiDuTemps.appel_verrouille_message', { heure: heureLimite })}
          </p>
        )}

        {/* Un enseignant qui voit trois fois plus d'élèves que sa classe doit
            comprendre pourquoi avant de pointer. */}
        {data.tronc_commun && (
          <p className="mb-3 rounded-xl bg-gold-50 px-3.5 py-2.5 text-sm text-gold-800">
            {t('emploiDuTemps.tronc_commun_appel', { count: data.classes.length })}{' '}
            <span className="font-semibold">{data.classes.map((c) => c.nom).join(' · ')}</span>
          </p>
        )}

        <div className="mb-3 flex items-center justify-between">
          <p className="text-sm text-navy-500">
            {t('emploiDuTemps.appel_resume', { total: lignes.length, presents: lignes.length - absents, absents })}
          </p>
        </div>

        <div className="flex flex-col divide-y divide-navy-50">
          {lignes.map((ligne) => {
            const present = ligne.statut === 'present'

            return (
              <div key={ligne.eleve_id} className="flex flex-wrap items-center justify-between gap-3 py-2.5">
                <span className={clsx('min-w-0 flex-1 truncate text-sm', present ? 'text-navy-800' : 'font-semibold text-red-600')}>
                  {ligne.nom_complet}
                  {/* Sur un tronc commun, deux élèves de classes différentes
                      peuvent porter le même nom : la classe lève le doute. */}
                  {data.tronc_commun && ligne.classe && (
                    <span className="ml-2 rounded bg-navy-50 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-navy-500">
                      {ligne.classe}
                    </span>
                  )}
                </span>

                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    disabled={verrouille}
                    onClick={() => marquerPresent(ligne.eleve_id)}
                    className={clsx(
                      'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                      verrouille && 'cursor-not-allowed opacity-60',
                      present ? 'bg-green-500 text-white shadow-sm' : 'bg-navy-50 text-navy-400 hover:bg-navy-100',
                    )}
                  >
                    {present && <Check className="h-3.5 w-3.5" />}
                    {t('emploiDuTemps.present')}
                  </button>

                  <details className={clsx('group relative', verrouille && 'pointer-events-none opacity-60')}>
                    <summary
                      className={clsx(
                        'flex list-none cursor-pointer items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors select-none',
                        !present ? 'bg-red-500 text-white shadow-sm' : 'bg-navy-50 text-navy-400 hover:bg-navy-100',
                      )}
                    >
                      {!present
                        ? t('emploiDuTemps.absent_motif', { motif: t(`emploiDuTemps.motifs.${ligne.motif ?? 'inconnu'}`) })
                        : t('emploiDuTemps.absent')}
                      <ChevronDown className="h-3.5 w-3.5" />
                    </summary>

                    <div className="absolute right-0 z-10 mt-1 w-40 overflow-hidden rounded-xl border border-navy-100 bg-white py-1 shadow-lifted">
                      {MOTIFS.map((cle) => (
                        <button
                          key={cle}
                          type="button"
                          onClick={(e) => {
                            marquerAbsent(ligne.eleve_id, cle as MotifAbsence)
                            e.currentTarget.closest('details')?.removeAttribute('open')
                          }}
                          className="block w-full px-3 py-1.5 text-left text-xs text-navy-700 hover:bg-cream-100"
                        >
                          {t(`emploiDuTemps.motifs.${cle}`)}
                        </button>
                      ))}
                    </div>
                  </details>
                </div>
              </div>
            )
          })}
        </div>
      </Card>

      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={() => navigate('/seances')}>
          {t('common.cancel')}
        </Button>
        <Button onClick={() => enregistrement.mutate()} disabled={verrouille || enregistrement.isPending || lignes.length === 0}>
          {t('emploiDuTemps.enregistrer_appel')}
        </Button>
      </div>
    </div>
  )
}
