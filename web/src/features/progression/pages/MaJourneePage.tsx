import { useEffect, useMemo, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { CalendarCheck, Save, Clock, Plus, ScanLine, ShieldCheck, Trash2 } from 'lucide-react'
import {
  fetchMesAffectations,
  fetchFeuilleJournee,
  enregistrerJournee,
  fetchHeuresCouverturePeriodes,
  MOTIFS,
  type MotifAbsence,
  type LigneAppel,
  type HeuresCouverturePeriode,
  type HeuresCouverturePeriodes,
} from '@/features/progression/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Select, Input, Textarea } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { useAuthStore } from '@/shared/store/authStore'
import { succes, erreur } from '@/shared/lib/alertes'
import { newActionId } from '@/shared/lib/actionId'
import type { ApiError } from '@/shared/types/api'

const LIBELLES_PERIODES: Record<keyof HeuresCouverturePeriodes, string> = {
  jour: 'Jour',
  semaine: 'Semaine',
  mois: 'Mois',
  annee: 'Année',
}

/** Une carte prévu/réalisé pour une période (jour, semaine, mois ou année). */
function CartePeriode({ libelle, stats }: { libelle: string; stats: HeuresCouverturePeriode }) {
  return (
    <div className="flex flex-1 flex-col gap-2 rounded-xl border border-navy-100/70 bg-white p-3.5 shadow-card">
      <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">{libelle}</p>
      <p className="text-sm text-navy-700">
        <span className="font-bold tabular-nums text-navy-900">{stats.heures_realisees}h</span>
        {' / '}
        <span className="font-semibold tabular-nums">{stats.heures_prevues}h</span>
      </p>
      <div className="flex items-center gap-2">
        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-100">
          <div
            className={`h-full rounded-full ${stats.taux >= 90 ? 'bg-green-500' : stats.taux >= 60 ? 'bg-gold-500' : 'bg-red-500'}`}
            style={{ width: `${Math.min(100, stats.taux)}%` }}
          />
        </div>
        <span className="text-xs font-semibold tabular-nums text-navy-600">{stats.taux}%</span>
      </div>
      {stats.seances_en_retard > 0 && (
        <span className="text-xs font-semibold text-red-600">
          {stats.seances_en_retard} séance{stats.seances_en_retard > 1 ? 's' : ''} en retard
        </span>
      )}
    </div>
  )
}

/** Heures de cours prévues vs réalisées de l'enseignant, pour le jour, la semaine, le mois et l'année en cours. */
function CouvertureStats() {
  const { data } = useQuery({ queryKey: ['heures-couverture-periodes'], queryFn: fetchHeuresCouverturePeriodes })

  if (!data || data.annee.heures_prevues === 0) return null

  return (
    <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-navy-100/70 bg-cream-50/50 p-4">
      <span className="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-navy-50 text-navy-600">
        <Clock className="h-4.5 w-4.5" />
      </span>
      <div className="flex flex-1 flex-wrap gap-3">
        {(Object.keys(LIBELLES_PERIODES) as (keyof HeuresCouverturePeriodes)[]).map((cle) => (
          <CartePeriode key={cle} libelle={LIBELLES_PERIODES[cle]} stats={data[cle]} />
        ))}
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
  const navigate = useNavigate()
  const estEnseignant = useAuthStore((s) => s.user?.est_enseignant ?? false)
  // Repli tant que la feuille du jour n'est pas chargée : la règle du compte,
  // résolue sans classe côté `/me`, donc aveugle aux règles par sous-système.
  const methodeDuCompte = useAuthStore((s) => s.user?.methode_validation_seance ?? 'qr')
  // Arrivée depuis le scan d'un QR code de salle : l'affectation résolue est
  // déjà connue, pas besoin de la faire choisir une seconde fois.
  const preselection = (location.state as { classeMatiereId?: number } | null)?.classeMatiereId
  const tokenScanne = (location.state as { qrToken?: string } | null)?.qrToken ?? null

  const todayIso = useMemo(() => new Date().toISOString().slice(0, 10), [])
  const [affectationId, setAffectationId] = useState<number | ''>(preselection ?? '')
  const [date, setDate] = useState(todayIso)
  const [qrToken, setQrToken] = useState<string | null>(tokenScanne)
  const [codeSalle, setCodeSalle] = useState('')
  const [lecons, setLecons] = useState<Set<number>>(new Set())
  const [contenu, setContenu] = useState('')
  const [titreLecon, setTitreLecon] = useState('')
  const [leconsAjoutees, setLeconsAjoutees] = useState<{ id: number; titre: string; operationId: string }[]>([])
  const [appel, setAppel] = useState<LigneAppel[]>([])
  const [observations, setObservations] = useState('')
  const [donneesPersonnalisees, setDonneesPersonnalisees] = useState<Record<string, string | number | boolean>>({})
  const [submitting, setSubmitting] = useState(false)

  const { data: affectations, isLoading: chargementAffectations } = useQuery({
    queryKey: ['mes-affectations', date],
    queryFn: () => fetchMesAffectations(date),
  })

  // Un seul rattachement ce jour-là : rien à choisir.
  useEffect(() => {
    if (affectationId === '' && affectations?.length === 1) {
      setAffectationId(affectations[0].classe_matiere_id)
    }
  }, [affectations, affectationId])

  // La liste dépend de l'emploi du temps du jour : un changement de date peut
  // faire disparaître le créneau sélectionné.
  useEffect(() => {
    if (affectationId === '' || !affectations) return
    if (!affectations.some((a) => a.classe_matiere_id === affectationId)) {
      setAffectationId('')
    }
  }, [affectations, affectationId])

  const {
    data: feuille,
    isLoading,
    isError,
    error: erreurFeuille,
    refetch,
  } = useQuery({
    queryKey: ['feuille-journee', affectationId, date],
    queryFn: () => fetchFeuilleJournee(Number(affectationId), date),
    enabled: affectationId !== '',
    retry: false,
  })

  // La règle de CE cours fait foi : c'est elle que le serveur exigera, et
  // elle seule tient compte du sous-système de la classe. Verrouiller sur
  // celle du compte, c'était masquer le champ de preuve pour un enseignant
  // que le serveur allait refuser — ou l'exiger alors qu'il en est dispensé.
  const methodeValidation = feuille?.seance.methode_validation_seance ?? methodeDuCompte
  const preuveExigee = methodeValidation !== 'libre'
  const preuveFournie = !preuveExigee || qrToken !== null || (methodeValidation === 'code' && codeSalle.trim() !== '')

  useEffect(() => {
    if (!feuille) return
    setLecons(new Set(feuille.lecons.filter((l) => l.faite_aujourdhui).map((l) => l.id)))
    setAppel(feuille.appel)
    setContenu(feuille.seance.contenu ?? '')
    setObservations(feuille.seance.observations ?? '')
    setDonneesPersonnalisees(feuille.seance.donnees_personnalisees ?? {})
    setLeconsAjoutees([])
    setTitreLecon('')
  }, [feuille])

  const leconsDisponibles = [
    ...(feuille?.lecons ?? []),
    ...leconsAjoutees.map((l) => ({ ...l, chemin: '', sequence: null, deja_traitee: false, faite_aujourdhui: false, validee_par: null })),
  ]
  const leconRenseignee = leconsDisponibles.some((l) => lecons.has(l.id))

  const ajouterLecon = () => {
    const titre = titreLecon.trim()
    if (!titre || submitting) return
    const id = Math.min(0, ...leconsAjoutees.map((l) => l.id)) - 1
    setLeconsAjoutees((lignes) => [...lignes, { id, titre, operationId: newActionId() }])
    setLecons((ids) => new Set([...ids, id]))
    setTitreLecon('')
  }

  const retirerNouvelleLecon = (id: number) => {
    setLeconsAjoutees((lignes) => lignes.filter((l) => l.id !== id))
    setLecons((ids) => new Set([...ids].filter((leconId) => leconId !== id)))
  }

  const basculerLecon = (id: number) => {
    setLecons((s) => {
      const copie = new Set(s)
      if (copie.has(id)) copie.delete(id)
      else copie.add(id)
      return copie
    })
  }

  const changerAffectation = (value: string) => {
    const id = value ? Number(value) : ''
    setAffectationId(id)
    if (id !== preselection) setQrToken(null)
    setCodeSalle('')
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
    if (!leconRenseignee || submitting) return
    setSubmitting(true)
    try {
      await enregistrerJournee(Number(affectationId), {
        date,
        lecons: [...lecons],
        actions_lecons: leconsAjoutees.map((l) => ({
          operation_id: l.operationId,
          action: 'creer',
          lecon_id: l.id,
          fiche: { titre: l.titre, date_prevue: date },
        })),
        contenu: contenu.trim() || null,
        qr_token: qrToken,
        code_salle: codeSalle || undefined,
        appel: appel.map((l) => ({ eleve_id: l.eleve_id, statut: l.statut, motif: l.motif })),
        observations: observations || null,
        donnees_personnalisees: donneesPersonnalisees,
      })
      succes(t('journee.saved'))
      await refetch()
    } catch (err) {
      erreur((err as ApiError).message)
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
        <EmptyState label={t(date === todayIso ? 'journee.aucune_affectation' : 'journee.aucun_creneau_ce_jour')} />
      ) : (
        <>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Select
              label={t('journee.classe_matiere')}
              value={affectationId}
              onChange={(e) => changerAffectation(e.target.value)}
            >
              <option value="">—</option>
              {affectations.map((a) => (
                <option key={a.classe_matiere_id} value={a.classe_matiere_id}>
                  {a.classe} — {a.matiere} ({a.heure_debut} - {a.heure_fin})
                </option>
              ))}
            </Select>
            <Input
              type="date"
              label={t('journee.date')}
              value={date}
              disabled={estEnseignant}
              max={todayIso}
              onChange={(e) => {
                setDate(e.target.value)
                if (e.target.value !== todayIso) setQrToken(null)
              }}
            />
          </div>

          {feuille && (
            <div className="flex items-center gap-2 text-sm text-navy-500">
              <Clock className="h-4 w-4" />
              <span>
                {t('journee.horaire')} :{' '}
                <span className="font-semibold text-navy-800">
                  {feuille.seance.heure_debut.slice(0, 5)} - {feuille.seance.heure_fin.slice(0, 5)}
                </span>
              </span>
            </div>
          )}

          {feuille && methodeValidation !== 'libre' && (
            <div className="rounded-xl border border-navy-100/70 bg-white p-3.5">
              {methodeValidation === 'code' ? (
                <div className="flex flex-wrap items-end gap-3">
                  <Input
                    label="Code de la salle"
                    placeholder="123456"
                    inputMode="numeric"
                    maxLength={6}
                    value={codeSalle}
                    onChange={(e) => setCodeSalle(e.target.value)}
                    className="max-w-[10rem]"
                  />
                  <p className="pb-2.5 text-xs text-navy-400">
                    Saisissez le code affiché à côté du QR de la salle pour prouver votre présence.
                  </p>
                </div>
              ) : qrToken ? (
                <p className="flex items-center gap-2 text-sm font-medium text-green-600">
                  <ShieldCheck className="h-4 w-4" />
                  QR de la salle scanné — présence confirmée.
                </p>
              ) : (
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="flex items-center gap-2 text-sm text-navy-500">
                    <ScanLine className="h-4 w-4 flex-none" />
                    Scannez le QR de la salle avant d'enregistrer.
                  </p>
                  <Button type="button" size="sm" variant="secondary" onClick={() => navigate('/scanner-qr')}>
                    <ScanLine className="h-4 w-4" />
                    Scanner
                  </Button>
                </div>
              )}
            </div>
          )}

          {affectationId === '' ? (
            <EmptyState label={t('journee.choisir')} />
          ) : isError ? (
            <EmptyState label={(erreurFeuille as unknown as ApiError)?.message ?? t('journee.aucun_creneau_ce_jour')} />
          ) : isLoading || !feuille ? (
            <Spinner />
          ) : (
            <>
              <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
                <h2 className="mb-3 font-display text-base font-bold text-navy-800">{t('journee.lecons')}</h2>
                <p className="mb-3 text-sm text-navy-500">{t('journee.lecon_obligatoire')}</p>
                {leconsDisponibles.length === 0 ? (
                  <p className="pb-2 text-sm text-navy-400">{t('journee.aucun_programme')}</p>
                ) : (
                  <div className="flex flex-col gap-1.5">
                    {leconsDisponibles.map((lecon) => (
                      <div
                        key={lecon.id}
                        className="flex items-start gap-2 rounded-xl px-2 py-1.5 hover:bg-cream-50"
                      >
                      <label className="flex min-w-0 flex-1 cursor-pointer items-start gap-3">
                        <input
                          type="checkbox"
                          checked={lecons.has(lecon.id)}
                          disabled={submitting}
                          onChange={() => basculerLecon(lecon.id)}
                          className="mt-0.5 h-4 w-4 flex-none rounded border-navy-300"
                        />
                        <span className="min-w-0">
                          <span className="text-sm font-medium text-navy-800">{lecon.titre}</span>
                          {lecon.deja_traitee && !lecon.faite_aujourdhui && (
                            <span className="ml-2 rounded-full bg-green-50 px-2 py-0.5 text-[0.625rem] font-semibold text-green-600">
                              {t('progression.traitee')}
                            </span>
                          )}
                          <span className="block text-xs text-navy-400">
                            {[lecon.chemin, lecon.sequence].filter(Boolean).join(' · ')}
                          </span>
                          {lecon.faite_aujourdhui && lecon.validee_par && (
                            <span className="block text-xs font-medium text-green-600">
                              {t('progression.validee_par', { nom: lecon.validee_par })}
                            </span>
                          )}
                        </span>
                      </label>
                      {lecon.id < 0 && (
                        <button type="button" title={t('journee.retirer_nouvelle_lecon')} disabled={submitting} onClick={() => retirerNouvelleLecon(lecon.id)} className="rounded-lg p-1.5 text-navy-400 hover:bg-red-50 hover:text-red-600">
                          <Trash2 className="h-4 w-4" />
                        </button>
                      )}
                      </div>
                    ))}
                  </div>
                )}
                <div className="mt-3 flex flex-wrap items-end gap-2 rounded-xl bg-cream-50 p-3">
                  <div className="min-w-0 flex-1">
                    <Input label={t('journee.titre_nouvelle_lecon')} maxLength={255} value={titreLecon} disabled={submitting} onChange={(e) => setTitreLecon(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); ajouterLecon() } }} />
                  </div>
                  <Button type="button" variant="secondary" disabled={!titreLecon.trim() || submitting} onClick={ajouterLecon}>
                    <Plus className="h-4 w-4" />{t('journee.ajouter_lecon_faite')}
                  </Button>
                </div>
                <Textarea
                  className="mt-3"
                  label={t('journee.complement_lecon')}
                  value={contenu}
                  onChange={(e) => setContenu(e.target.value)}
                  maxLength={2000}
                  rows={2}
                  placeholder={t(feuille.lecons.length === 0 ? 'journee.contenu_placeholder' : 'journee.contenu_hors_programme')}
                />
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
                {/* Le serveur refuse en 403 sans preuve : mieux vaut un
                    bouton inactif qu'un aller-retour qui perd la saisie. */}
                <Button onClick={enregistrer} disabled={submitting || !preuveFournie || !leconRenseignee}>
                  <Save className="h-4 w-4" />
                  {t('common.save')}
                </Button>
              </div>
            </>
          )}
        </>
      )}
    </div>
  )
}
