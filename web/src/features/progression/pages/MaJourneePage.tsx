import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { CalendarCheck, Save } from 'lucide-react'
import {
  fetchMesAffectations,
  fetchFeuilleJournee,
  enregistrerJournee,
  MOTIFS,
  type MotifAbsence,
  type LigneAppel,
} from '@/features/progression/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Select, Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

/**
 * L'enseignant déclare ce qu'il vient de faire : les leçons traitées et
 * l'appel. Tous les élèves sont présents par défaut — l'appel ne relève que
 * les écarts, et une absence exige son motif.
 */
export function MaJourneePage() {
  const { t } = useTranslation()

  const [affectationId, setAffectationId] = useState<number | ''>('')
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [lecons, setLecons] = useState<Set<number>>(new Set())
  const [appel, setAppel] = useState<LigneAppel[]>([])
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
