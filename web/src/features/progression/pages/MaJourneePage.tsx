import { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { CalendarCheck, Save, Clock } from 'lucide-react'
import {
  fetchMesAffectations,
  fetchFeuilleJournee,
  enregistrerJournee,
  fetchHeuresCouverture,
  MOTIFS,
  type MotifAbsence,
  type LigneAppel,
} from '@/features/progression/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Select, Input, Textarea } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

/** Heures de cours prévues vs réalisées de l'enseignant, depuis le début de l'année. */
function CouvertureStats() {
  const { data } = useQuery({ queryKey: ['heures-couverture'], queryFn: fetchHeuresCouverture })

  if (!data || data.heures_prevues === 0) return null

  return (
    <div className="flex flex-wrap items-center gap-4 rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
      <span className="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-navy-50 text-navy-600">
        <Clock className="h-4.5 w-4.5" />
      </span>
      <div className="flex flex-1 flex-wrap items-center gap-x-6 gap-y-1">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">Heures de couverture</p>
          <p className="text-sm text-navy-700">
            <span className="font-bold tabular-nums text-navy-900">{data.heures_realisees}h</span>
            {' réalisées sur '}
            <span className="font-semibold tabular-nums">{data.heures_prevues}h</span>
            {' prévues'}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <div className="h-2 w-28 overflow-hidden rounded-full bg-navy-100">
            <div
              className={`h-full rounded-full ${data.taux >= 90 ? 'bg-green-500' : data.taux >= 60 ? 'bg-gold-500' : 'bg-red-500'}`}
              style={{ width: `${Math.min(100, data.taux)}%` }}
            />
          </div>
          <span className="text-xs font-semibold tabular-nums text-navy-600">{data.taux}%</span>
        </div>
        {data.seances_en_retard > 0 && (
          <span className="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-600">
            {data.seances_en_retard} séance{data.seances_en_retard > 1 ? 's' : ''} en retard
          </span>
        )}
      </div>
    </div>
  )
}

/**
 * L'enseignant déclare ce qu'il vient de faire : les leçons traitées et
 * l'appel. Tous les élèves sont présents par défaut — l'appel ne relève que
 * les écarts, et une absence exige son motif.
 */
export function MaJourneePage() {
  const { t } = useTranslation()
  const location = useLocation()
  // Arrivée depuis le scan d'un QR code de salle : l'affectation résolue est
  // déjà connue, pas besoin de la faire choisir une seconde fois.
  const preselection = (location.state as { classeMatiereId?: number } | null)?.classeMatiereId

  const [affectationId, setAffectationId] = useState<number | ''>(preselection ?? '')
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [lecons, setLecons] = useState<Set<number>>(new Set())
  const [appel, setAppel] = useState<LigneAppel[]>([])
  const [observations, setObservations] = useState('')
  const [donneesPersonnalisees, setDonneesPersonnalisees] = useState<Record<string, string | number | boolean>>({})
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  const { data: affectations, isLoading: chargementAffectations } = useQuery({
    queryKey: ['mes-affectations'],
    queryFn: fetchMesAffectations,
  })

  // Un seul rattachement — cas du titulaire au primaire : rien à choisir.
  useEffect(() => {
    if (affectationId === '' && affectations?.length === 1) {
      setAffectationId(affectations[0].classe_matiere_id)
    }
  }, [affectations, affectationId])

  const { data: feuille, isLoading, refetch } = useQuery({
    queryKey: ['feuille-journee', affectationId, date],
    queryFn: () => fetchFeuilleJournee(Number(affectationId), date),
    enabled: affectationId !== '',
  })

  useEffect(() => {
    if (!feuille) return
    setLecons(new Set(feuille.lecons.filter((l) => l.faite_aujourdhui).map((l) => l.id)))
    setAppel(feuille.appel)
    setObservations(feuille.seance.observations ?? '')
    setDonneesPersonnalisees(feuille.seance.donnees_personnalisees ?? {})
  }, [feuille])

  const basculerLecon = (id: number) => {
    setLecons((s) => {
      const copie = new Set(s)
      copie.has(id) ? copie.delete(id) : copie.add(id)
      return copie
    })
  }

  const changerStatut = (eleveId: number, present: boolean) => {
    setAppel((lignes) =>
      lignes.map((l) =>
        l.eleve_id === eleveId
          ? {
              ...l,
              statut: present ? 'present' : 'absent',
              // Un motif par défaut évite d'enregistrer une absence muette ;
              // l'enseignant le précise ensuite s'il le connaît.
              motif: present ? null : (l.motif ?? 'inconnu'),
            }
          : l,
      ),
    )
  }

  const changerMotif = (eleveId: number, motif: MotifAbsence) => {
    setAppel((lignes) => lignes.map((l) => (l.eleve_id === eleveId ? { ...l, motif } : l)))
  }

  const enregistrer = async () => {
    setSubmitting(true)
    setMessage(null)
    setErreur(null)
    try {
      await enregistrerJournee(Number(affectationId), {
        date,
        lecons: [...lecons],
        appel: appel.map((l) => ({ eleve_id: l.eleve_id, statut: l.statut, motif: l.motif })),
        observations: observations || null,
        donnees_personnalisees: donneesPersonnalisees,
      })
      setMessage(t('journee.saved'))
      refetch()
    } catch (err) {
      setErreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  const absents = appel.filter((l) => l.statut !== 'present').length

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('journee.title')} sousTitre={t('journee.hint')} icon={CalendarCheck} />

      <CouvertureStats />

      {chargementAffectations ? (
        <Spinner />
      ) : !affectations || affectations.length === 0 ? (
        <EmptyState label={t('journee.aucune_affectation')} />
      ) : (
        <>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Select
              label={t('journee.classe_matiere')}
              value={affectationId}
              onChange={(e) => setAffectationId(e.target.value ? Number(e.target.value) : '')}
            >
              <option value="">—</option>
              {affectations.map((a) => (
                <option key={a.classe_matiere_id} value={a.classe_matiere_id}>
                  {a.classe} — {a.matiere}
                </option>
              ))}
            </Select>
            <Input type="date" label={t('journee.date')} value={date} onChange={(e) => setDate(e.target.value)} />
          </div>

          {affectationId === '' ? (
            <EmptyState label={t('journee.choisir')} />
          ) : isLoading || !feuille ? (
            <Spinner />
          ) : (
            <>
              <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
                <h2 className="mb-3 font-display text-base font-bold text-navy-800">{t('journee.lecons')}</h2>
                {feuille.lecons.length === 0 ? (
                  <p className="py-4 text-center text-sm text-navy-400">{t('journee.aucun_programme')}</p>
                ) : (
                  <div className="flex flex-col gap-1.5">
                    {feuille.lecons.map((lecon) => (
                      <label
                        key={lecon.id}
                        className="flex cursor-pointer items-start gap-3 rounded-xl px-2 py-1.5 hover:bg-cream-50"
                      >
                        <input
                          type="checkbox"
                          checked={lecons.has(lecon.id)}
                          onChange={() => basculerLecon(lecon.id)}
                          className="mt-0.5 h-4 w-4 flex-none rounded border-navy-300"
                        />
                        <span className="min-w-0">
                          <span className="text-sm font-medium text-navy-800">{lecon.titre}</span>
                          {lecon.deja_traitee && !lecon.faite_aujourdhui && (
                            <span className="ml-2 rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-semibold text-green-600">
                              {t('progression.traitee')}
                            </span>
                          )}
                          <span className="block text-xs text-navy-400">
                            {[lecon.chemin, lecon.sequence].filter(Boolean).join(' · ')}
                          </span>
                        </span>
                      </label>
                    ))}
                  </div>
                )}
              </div>

              <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
                <div className="mb-3 flex items-center justify-between">
                  <h2 className="font-display text-base font-bold text-navy-800">{t('journee.appel')}</h2>
                  <span className="text-xs text-navy-400">
                    {t('journee.resume_appel', { presents: appel.length - absents, absents })}
                  </span>
                </div>

                <div className="flex flex-col divide-y divide-navy-50">
                  {appel.map((ligne) => {
                    const present = ligne.statut === 'present'

                    return (
                      <div key={ligne.eleve_id} className="flex flex-wrap items-center gap-3 py-2">
                        <label className="flex flex-1 cursor-pointer items-center gap-3">
                          <input
                            type="checkbox"
                            checked={present}
                            onChange={(e) => changerStatut(ligne.eleve_id, e.target.checked)}
                            className="h-4 w-4 flex-none rounded border-navy-300"
                          />
                          <span className={`text-sm ${present ? 'text-navy-800' : 'font-semibold text-red-600'}`}>
                            {ligne.nom_complet}
                          </span>
                        </label>

                        {!present && (
                          <select
                            value={ligne.motif ?? 'inconnu'}
                            onChange={(e) => changerMotif(ligne.eleve_id, e.target.value as MotifAbsence)}
                            className="rounded-lg border border-red-200 bg-red-50/50 px-2 py-1 text-xs font-medium text-red-700 focus:border-red-400 focus:outline-none"
                          >
                            {Object.entries(MOTIFS).map(([cle, libelle]) => (
                              <option key={cle} value={cle}>
                                {libelle}
                              </option>
                            ))}
                          </select>
                        )}
                      </div>
                    )
                  })}
                </div>
              </div>

              <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
                <h2 className="mb-3 font-display text-base font-bold text-navy-800">Observations</h2>

                  {feuille.champs_personnalises.length > 0 && (
                    <div className="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                      {feuille.champs_personnalises.map((champ) => (
                        <label key={champ.id} className="flex flex-col gap-1.5">
                          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
                            {champ.libelle}
                          </span>
                          {champ.type === 'case' ? (
                            <input
                              type="checkbox"
                              checked={Boolean(donneesPersonnalisees[champ.id])}
                              onChange={(e) =>
                                setDonneesPersonnalisees((d) => ({ ...d, [champ.id]: e.target.checked }))
                              }
                              className="h-4 w-4 rounded border-navy-300"
                            />
                          ) : (
                            <input
                              type={champ.type === 'nombre' ? 'number' : 'text'}
                              value={(donneesPersonnalisees[champ.id] as string | number | undefined) ?? ''}
                              onChange={(e) =>
                                setDonneesPersonnalisees((d) => ({
                                  ...d,
                                  [champ.id]: champ.type === 'nombre' ? Number(e.target.value) : e.target.value,
                                }))
                              }
                              className="rounded-lg border border-navy-200 px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
                            />
                          )}
                        </label>
                      ))}
                    </div>
                  )}

                  <Textarea
                    label="Note de fin de cours"
                    value={observations}
                    onChange={(e) => setObservations(e.target.value)}
                    placeholder="Difficultés rencontrées, points à revoir, comportement de la classe…"
                  />
              </div>

              <div className="flex items-center gap-3">
                <Button onClick={enregistrer} disabled={submitting}>
                  <Save className="h-4 w-4" />
                  {t('common.save')}
                </Button>
                {message && <span className="text-sm text-green-600">{message}</span>}
                {erreur && <span className="text-sm text-red-500">{erreur}</span>}
              </div>
            </>
          )}
        </>
      )}
    </div>
  )
}
