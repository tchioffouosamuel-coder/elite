import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarClock, CheckCircle2, ChevronLeft, ChevronRight, ClipboardCheck, ScanLine, ShieldCheck, Users } from 'lucide-react'
import {
  fetchJourneeEcole,
  fetchFeuilleJournee,
  enregistrerJournee,
  MOTIFS,
  type CoursJourAdmin,
  type MotifAbsence,
  type LigneAppel,
  type StatutCoursJour,
} from '@/features/progression/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { Textarea } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, EmptyState, ErrorState } from '@/shared/ui/Feedback'
import { useAuthStore } from '@/shared/store/authStore'
import { succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function ajouterJours(iso: string, delta: number): string {
  const [a, m, j] = iso.split('-').map(Number)
  const date = new Date(a, m - 1, j + delta)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const BADGES_STATUT: Record<StatutCoursJour, { label: string; classe: string }> = {
  effectuee: { label: 'Fait', classe: 'bg-green-50 text-green-700' },
  prevue: { label: 'À venir', classe: 'bg-navy-50 text-navy-600' },
  en_retard: { label: 'En retard', classe: 'bg-red-50 text-red-600' },
  annulee: { label: 'Annulé', classe: 'bg-navy-100 text-navy-400' },
}

function BadgeStatut({ statut }: { statut: StatutCoursJour }) {
  const badge = BADGES_STATUT[statut]
  return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${badge.classe}`}>{badge.label}</span>
}

/**
 * Feuille d'un cours choisi dans la liste : leçons à cocher et appel de la
 * classe — même écran que « Ma journée » (l'enseignant), ouvert ici sur
 * n'importe quelle affectation grâce à `MaJourneeService::peutIntervenir()`.
 */
function FeuilleModal({ cours, date, onClose, onEnregistre }: { cours: CoursJourAdmin; date: string; onClose: () => void; onEnregistre: () => void }) {
  const methodeValidation = useAuthStore((s) => s.user?.methode_validation_seance ?? 'libre')
  const navigate = useNavigate()
  const [lecons, setLecons] = useState<Set<number>>(new Set())
  const [appel, setAppel] = useState<LigneAppel[]>([])
  const [observations, setObservations] = useState('')
  const [codeSalle, setCodeSalle] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const { data: feuille, isLoading, isError, error: erreurFeuille, refetch } = useQuery({
    queryKey: ['feuille-journee', cours.classe_matiere_id, date],
    queryFn: () => fetchFeuilleJournee(cours.classe_matiere_id, date),
    retry: false,
  })

  useEffect(() => {
    if (!feuille) return
    setLecons(new Set(feuille.lecons.filter((l) => l.faite_aujourdhui).map((l) => l.id)))
    setAppel(feuille.appel)
    setObservations(feuille.seance.observations ?? '')
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
          ? { ...l, statut: present ? 'present' : 'absent', motif: present ? null : (l.motif ?? 'inconnu') }
          : l,
      ),
    )
  }

  const changerMotif = (eleveId: number, motif: MotifAbsence) => {
    setAppel((lignes) => lignes.map((l) => (l.eleve_id === eleveId ? { ...l, motif } : l)))
  }

  const enregistrer = async () => {
    setSubmitting(true)
    try {
      await enregistrerJournee(cours.classe_matiere_id, {
        date,
        lecons: [...lecons],
        code_salle: codeSalle || undefined,
        appel: appel.map((l) => ({ eleve_id: l.eleve_id, statut: l.statut, motif: l.motif })),
        observations: observations || null,
      })
      succes('Journée enregistrée.')
      onEnregistre()
      refetch()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  const absents = appel.filter((l) => l.statut !== 'present').length

  return (
    <Modal title={`${cours.classe ?? '—'} — ${cours.matiere ?? '—'}`} onClose={onClose} taille="lg">
      {isLoading ? (
        <Spinner />
      ) : isError || !feuille ? (
        <EmptyState label={(erreurFeuille as unknown as ApiError)?.message ?? "Ce cours n'a pas pu être ouvert."} />
      ) : (
        <div className="flex flex-col gap-5">
          <p className="flex items-center gap-2 text-sm text-navy-500">
            <CalendarClock className="h-4 w-4" />
            {feuille.seance.heure_debut.slice(0, 5)} - {feuille.seance.heure_fin.slice(0, 5)}
            {cours.enseignant && <span> · {cours.enseignant}</span>}
            {cours.salle && <span className="rounded-lg bg-navy-50 px-2 py-0.5 text-xs text-navy-500">{cours.salle}</span>}
          </p>

          {methodeValidation === 'code' && (
            <div className="flex flex-wrap items-end gap-3 rounded-xl border border-navy-100/70 bg-white p-3.5">
              <label className="flex flex-col gap-1.5 text-sm font-semibold text-navy-700">
                Code de la salle
                <input
                  value={codeSalle}
                  onChange={(e) => setCodeSalle(e.target.value)}
                  placeholder="123456"
                  inputMode="numeric"
                  maxLength={6}
                  className="max-w-[10rem] rounded-xl border border-navy-200 px-3 py-2.5 font-normal text-navy-800 focus:border-navy-400 focus:outline-none"
                />
              </label>
              <p className="pb-2.5 text-xs text-navy-400">Saisissez le code affiché à côté du QR de la salle.</p>
            </div>
          )}

          {methodeValidation === 'qr' && (
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-navy-100/70 bg-white p-3.5">
              <p className="flex items-center gap-2 text-sm text-navy-500">
                <ScanLine className="h-4 w-4 flex-none" />
                Scannez le QR de la salle avant d'enregistrer.
              </p>
              <Button
                type="button"
                size="sm"
                variant="secondary"
                onClick={() => navigate('/scanner-qr', { state: { classeMatiereId: cours.classe_matiere_id } })}
              >
                <ScanLine className="h-4 w-4" />
                Scanner
              </Button>
            </div>
          )}

          <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
            <h3 className="mb-3 font-display text-base font-bold text-navy-800">Leçons traitées</h3>
            {feuille.lecons.length === 0 ? (
              <p className="py-4 text-center text-sm text-navy-400">Aucune leçon au programme de cette matière.</p>
            ) : (
              <div className="flex flex-col gap-1.5">
                {feuille.lecons.map((lecon) => (
                  <label key={lecon.id} className="flex cursor-pointer items-start gap-3 rounded-xl px-2 py-1.5 hover:bg-cream-50">
                    <input
                      type="checkbox"
                      checked={lecons.has(lecon.id)}
                      onChange={() => basculerLecon(lecon.id)}
                      className="mt-0.5 h-4 w-4 flex-none rounded border-navy-300"
                    />
                    <span className="min-w-0">
                      <span className="text-sm font-medium text-navy-800">{lecon.titre}</span>
                      {lecon.deja_traitee && !lecon.faite_aujourdhui && (
                        <span className="ml-2 rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-semibold text-green-600">Traitée</span>
                      )}
                      <span className="block text-xs text-navy-400">{[lecon.chemin, lecon.sequence].filter(Boolean).join(' · ')}</span>
                    </span>
                  </label>
                ))}
              </div>
            )}
          </div>

          <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="font-display text-base font-bold text-navy-800">Appel</h3>
              <span className="text-xs text-navy-400">{appel.length - absents} présent(s), {absents} absent(s)</span>
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
                      <span className={`text-sm ${present ? 'text-navy-800' : 'font-semibold text-red-600'}`}>{ligne.nom_complet}</span>
                    </label>
                    {!present && (
                      <select
                        value={ligne.motif ?? 'inconnu'}
                        onChange={(e) => changerMotif(ligne.eleve_id, e.target.value as MotifAbsence)}
                        className="rounded-lg border border-red-200 bg-red-50/50 px-2 py-1 text-xs font-medium text-red-700 focus:border-red-400 focus:outline-none"
                      >
                        {Object.entries(MOTIFS).map(([cle, libelle]) => (
                          <option key={cle} value={cle}>{libelle}</option>
                        ))}
                      </select>
                    )}
                  </div>
                )
              })}
            </div>
          </div>

          <Textarea label="Note de fin de cours" value={observations} onChange={(e) => setObservations(e.target.value)} placeholder="Difficultés rencontrées, points à revoir, comportement de la classe…" />

          {feuille.seance.verrouille ? (
            <p className="flex items-center gap-2 text-sm font-medium text-navy-400">
              <ShieldCheck className="h-4 w-4" />
              Déclaration verrouillée — enregistrement désactivé.
            </p>
          ) : (
            <div className="flex justify-end">
              <Button onClick={enregistrer} disabled={submitting}>
                <CheckCircle2 className="h-4 w-4" />
                Enregistrer
              </Button>
            </div>
          )}
        </div>
      )}
    </Modal>
  )
}

/**
 * Consultation quotidienne, pour la direction : tous les cours prévus ce
 * jour-là dans l'école, leur statut réel, et un accès direct pour valider
 * une leçon ou faire l'appel à la place de l'enseignant si besoin.
 */
export function JourneeEcolePage() {
  const queryClient = useQueryClient()
  const todayIso = useMemo(() => new Date().toISOString().slice(0, 10), [])
  const [date, setDate] = useState(todayIso)
  const [coursOuvert, setCoursOuvert] = useState<CoursJourAdmin | null>(null)

  const { data: cours, isLoading, isError, error: erreurCours } = useQuery({
    queryKey: ['journee-ecole', date],
    queryFn: () => fetchJourneeEcole(date),
  })

  const resume = useMemo(() => {
    const liste = cours ?? []
    return {
      total: liste.length,
      effectues: liste.filter((c) => c.statut === 'effectuee').length,
      enRetard: liste.filter((c) => c.statut === 'en_retard').length,
    }
  }, [cours])

  const onEnregistre = () => {
    queryClient.invalidateQueries({ queryKey: ['journee-ecole', date] })
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Journée de l'école"
        sousTitre="Les cours et leçons prévus aujourd'hui, avec leur statut — à consulter au quotidien."
        icon={ClipboardCheck}
      />

      <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-navy-100/70 bg-white p-3.5 shadow-card">
        <div className="flex items-center gap-1.5">
          <Button type="button" variant="ghost" size="sm" onClick={() => setDate((d) => ajouterJours(d, -1))} title="Jour précédent">
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            className="rounded-xl border border-navy-200 px-3 py-2 text-sm font-semibold text-navy-800 focus:border-navy-400 focus:outline-none"
          />
          <Button type="button" variant="ghost" size="sm" onClick={() => setDate((d) => ajouterJours(d, 1))} title="Jour suivant">
            <ChevronRight className="h-4 w-4" />
          </Button>
          {date !== todayIso && (
            <Button type="button" variant="secondary" size="sm" onClick={() => setDate(todayIso)}>
              Aujourd'hui
            </Button>
          )}
        </div>

        {cours && cours.length > 0 && (
          <div className="ml-auto flex flex-wrap items-center gap-2 text-xs font-semibold">
            <span className="rounded-full bg-navy-50 px-2.5 py-1 text-navy-600">{resume.total} cours</span>
            <span className="rounded-full bg-green-50 px-2.5 py-1 text-green-700">{resume.effectues} fait(s)</span>
            {resume.enRetard > 0 && <span className="rounded-full bg-red-50 px-2.5 py-1 text-red-600">{resume.enRetard} en retard</span>}
          </div>
        )}
      </div>

      {isLoading ? (
        <Spinner />
      ) : isError ? (
        <ErrorState message={(erreurCours as ApiError)?.message} />
      ) : !cours || cours.length === 0 ? (
        <EmptyState label="Aucun cours prévu à l'emploi du temps pour cette date." />
      ) : (
        <div className="overflow-hidden rounded-2xl border border-navy-100/70 bg-white shadow-card">
          <table className="w-full text-left text-sm">
            <thead className="bg-navy-50 text-xs uppercase tracking-wide text-navy-500">
              <tr>
                <th className="px-4 py-3">Horaire</th>
                <th className="px-4 py-3">Classe</th>
                <th className="px-4 py-3">Matière</th>
                <th className="px-4 py-3">Enseignant</th>
                <th className="px-4 py-3">Statut</th>
                <th className="px-4 py-3">Leçons</th>
                <th className="px-4 py-3">Appel</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-navy-50">
              {cours.map((c) => (
                <tr key={c.classe_matiere_id} className="hover:bg-cream-50/50">
                  <td className="whitespace-nowrap px-4 py-3 font-semibold text-navy-800">{c.heure_debut}–{c.heure_fin}</td>
                  <td className="px-4 py-3 text-navy-700">{c.classe ?? '—'}</td>
                  <td className="px-4 py-3 text-navy-600">{c.matiere ?? '—'}</td>
                  <td className="px-4 py-3 text-navy-500">{c.enseignant ?? 'Non assigné'}</td>
                  <td className="px-4 py-3"><BadgeStatut statut={c.statut} /></td>
                  <td className="px-4 py-3 text-navy-500">{c.lecons_traitees}</td>
                  <td className="px-4 py-3 text-navy-500">
                    {c.statut === 'effectuee' ? (
                      <span className="flex items-center gap-1.5"><Users className="h-3.5 w-3.5" />{c.eleves_pointes}</span>
                    ) : '—'}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Button type="button" variant="secondary" size="sm" onClick={() => setCoursOuvert(c)}>
                      {c.statut === 'effectuee' ? 'Voir' : "Faire l'appel"}
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {coursOuvert && (
        <FeuilleModal cours={coursOuvert} date={date} onClose={() => setCoursOuvert(null)} onEnregistre={onEnregistre} />
      )}
    </div>
  )
}
