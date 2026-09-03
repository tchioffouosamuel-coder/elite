import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { JOURS } from '@/features/emploiDuTemps/api'
import {
  FileDown,
  HeartPulse,
  UserRound,
  CalendarX,
  ShieldAlert,
  CalendarClock,
  Stethoscope,
  Gavel,
  ChevronRight,
  TrendingUp,
  GraduationCap,
} from 'lucide-react'
import {
  fetchMoi,
  fetchNotes,
  fetchEmploiDuTemps,
  fetchAbsences,
  fetchAssiduite,
  fetchVisitesInfirmerie,
  fetchSanctions,
  type JourAssiduite,
} from '@/features/eleve/api'
import { ouvrirDocument } from '@/shared/lib/download'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { erreur, info } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function Champ({ label, valeur }: { label: string; valeur: string | null | undefined }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">{label}</span>
      <span className="text-sm font-medium text-navy-800">{valeur || '—'}</span>
    </div>
  )
}

/**
 * Tout ce que voit un élève sur son propre dossier, sur un seul écran comme
 * `ParentEnfantPage` — mais sans le volet finance (réservé au tuteur), et en
 * lecture seule : pas de soumission de justification ni d'observation, un
 * élève consulte, il n'initie pas de démarche.
 */
export function EleveAccueilPage() {
  const { data: e, isLoading, isError } = useQuery({ queryKey: ['eleve-moi'], queryFn: fetchMoi })

  const voirBulletin = async () => {
    try {
      await ouvrirDocument('/eleve/bulletin')
    } catch (err) {
      const apiErr = err as ApiError
      if (apiErr.status === 422) info(apiErr.message)
      else erreur(apiErr.message)
    }
  }

  if (isLoading) return <Spinner />
  if (isError || !e) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <div className="flex flex-wrap items-center gap-3">
          {e.photo_url ? (
            <img src={e.photo_url} alt={e.nom_complet} className="h-14 w-14 rounded-full object-cover ring-1 ring-navy-100" />
          ) : (
            <span className="flex h-14 w-14 items-center justify-center rounded-full bg-navy-700 text-lg font-bold text-cream-50">
              {e.nom_complet.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()}
            </span>
          )}
          <div className="min-w-0">
            <h1 className="break-words font-display text-2xl font-bold tracking-tight text-navy-900">{e.nom_complet}</h1>
            <p className="break-words text-sm text-navy-400">{[e.matricule, e.classe?.nom, e.school?.name].filter(Boolean).join(' · ') || '—'}</p>
          </div>
          <div className="flex w-full flex-wrap items-center gap-2 sm:ml-auto sm:w-auto">
            {e.sante.aptitude === 'inapte' && <Badge tone="red">Inapte au sport / Unfit for sports</Badge>}
            <Button className="flex-1 sm:flex-none" variant="secondary" onClick={voirBulletin}>
              <FileDown className="h-4 w-4" />
              Bulletin / Report card
            </Button>
          </div>
        </div>
      </div>

      <Card>
        <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <UserRound className="h-4 w-4" />
          Identité / Identity
        </h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Champ label="Sexe / Sex" valeur={e.sexe === 'F' ? 'Féminin / Female' : 'Masculin / Male'} />
          <Champ label="Date de naissance / Date of birth" valeur={e.date_naissance} />
          <Champ label="Lieu de naissance / Place of birth" valeur={e.lieu_naissance} />
          <Champ label="Nationalité / Nationality" valeur={e.nationalite} />
          <Champ label="Adresse / Address" valeur={e.adresse} />
        </div>
      </Card>

      <Card>
        <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <HeartPulse className="h-4 w-4 text-gold-500" />
          Situation sanitaire / Health situation
        </h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Champ label="Groupe sanguin / Blood type" valeur={e.sante.groupe_sanguin} />
          <Champ label="Aptitude / Fitness" valeur={e.sante.aptitude === 'apte' ? 'Apte / Fit' : 'Inapte / Unfit'} />
          <div className="sm:col-span-2 lg:col-span-2">
            <Champ label="Allergies" valeur={e.sante.allergies} />
          </div>
          <div className="sm:col-span-2 lg:col-span-4">
            <Champ label="Situation sanitaire / Health situation" valeur={e.sante.situation_sanitaire} />
          </div>
        </div>
      </Card>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Tuteurs / Guardians</h2>
        <div className="flex flex-col divide-y divide-navy-100">
          {e.tuteurs.length === 0 ? (
            <p className="text-sm text-navy-400">Aucun tuteur enregistré. / No guardian recorded.</p>
          ) : (
            e.tuteurs.map((t, i) => (
              <div key={i} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div>
                  <p className="text-sm font-semibold text-navy-800">{t.nom_complet}</p>
                  <p className="text-xs text-navy-400">{t.lien_parente || '—'}</p>
                </div>
                {t.telephone && <span className="text-xs text-navy-500">{t.telephone}</span>}
              </div>
            ))
          )}
        </div>
      </Card>

      <NotesCard />
      <EmploiDuTempsCard />
      <TauxAssiduiteCard />
      <AssiduiteCard />
      <InfirmerieCard />
      <DisciplineCard />
    </div>
  )
}

function NotesCard() {
  const { data, isLoading, isError } = useQuery({ queryKey: ['eleve-notes'], queryFn: () => fetchNotes() })

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <GraduationCap className="h-4 w-4" />
        Notes / Grades
      </h2>
      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <p className="text-sm text-navy-400">Aucune note pour l'instant. / No grades yet.</p>
      ) : (
        <>
          <div className="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-cream-100 px-4 py-3">
            <span className="text-sm font-semibold text-navy-700">{data.trimestre.libelle}</span>
            <div className="flex items-center gap-4">
              <div className="text-right">
                <p className="text-[11px] uppercase tracking-wide text-navy-400">Moyenne / Average</p>
                <p className="text-lg font-bold tabular-nums text-navy-900">{data.moyenne_generale ?? '—'}</p>
              </div>
              <div className="text-right">
                <p className="text-[11px] uppercase tracking-wide text-navy-400">Rang / Rank</p>
                <p className="text-lg font-bold tabular-nums text-navy-900">{data.rang_general ?? '—'}</p>
              </div>
            </div>
          </div>

          <div className="overflow-x-auto rounded-xl border border-navy-100">
            <table className="w-full min-w-[420px] text-xs">
              <thead className="bg-cream-50 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                <tr>
                  <th className="px-2.5 py-2 text-left">Matière / Subject</th>
                  {data.sequences.map((s) => (
                    <th key={s.id} className="px-2.5 py-2 text-right">{s.libelle}</th>
                  ))}
                  <th className="px-2.5 py-2 text-right">Moy. / Avg</th>
                  <th className="px-2.5 py-2 text-right">Rang / Rank</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-navy-50">
                {(data.matieres ?? data.competences ?? []).map((ligne) => (
                  <tr key={'matiere_id' in ligne ? ligne.matiere_id : ligne.competence_id}>
                    <td className="px-2.5 py-1.5 font-medium text-navy-800">
                      {'matiere' in ligne ? ligne.matiere : ligne.competence}
                    </td>
                    {ligne.notes.map((n) => (
                      <td key={n.sequence_id} className="whitespace-nowrap px-2.5 py-1.5 text-right tabular-nums">
                        {n.appreciation ? n.appreciation.emoji : (n.valeur ?? n.total ?? '—')}
                      </td>
                    ))}
                    <td className="whitespace-nowrap px-2.5 py-1.5 text-right tabular-nums font-semibold">{ligne.moyenne ?? '—'}</td>
                    <td className="whitespace-nowrap px-2.5 py-1.5 text-right tabular-nums">{ligne.rang ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </Card>
  )
}

const MOIS_LABEL = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc']

function tauxPresence(jours: JourAssiduite[]): number {
  if (jours.length === 0) return 0
  const presents = jours.filter((j) => j.statut === 'present').length
  return Math.round((presents / jours.length) * 100)
}

function TauxAssiduiteCard() {
  const [moisOuvert, setMoisOuvert] = useState<string | null>(null)
  const { data: jours, isLoading } = useQuery({ queryKey: ['eleve-assiduite'], queryFn: fetchAssiduite })

  const parMois = new Map<string, JourAssiduite[]>()
  for (const j of jours ?? []) {
    const cle = j.date.slice(0, 7)
    if (!parMois.has(cle)) parMois.set(cle, [])
    parMois.get(cle)!.push(j)
  }
  const mois = [...parMois.entries()].sort(([a], [b]) => b.localeCompare(a))

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <TrendingUp className="h-4 w-4" />
        Taux d'assiduité / Attendance rate
      </h2>

      {isLoading ? (
        <Spinner />
      ) : !jours || jours.length === 0 ? (
        <p className="text-sm text-navy-400">Aucun pointage relevé pour l'instant. / No attendance recorded yet.</p>
      ) : (
        <>
          <div className="mb-4 flex items-center justify-between rounded-xl bg-cream-100 px-4 py-3">
            <span className="text-sm font-semibold text-navy-700">Sur l'année / For the year</span>
            <span className="text-2xl font-bold tabular-nums text-navy-900">{tauxPresence(jours)}%</span>
          </div>

          <div className="flex flex-col divide-y divide-navy-50">
            {mois.map(([cle, joursMois]) => {
              const [annee, m] = cle.split('-')
              const taux = tauxPresence(joursMois)
              return (
                <button
                  key={cle}
                  type="button"
                  onClick={() => setMoisOuvert(cle)}
                  className="flex items-center justify-between gap-3 py-2.5 text-left first:pt-0 last:pb-0 transition-colors hover:bg-cream-50/80"
                >
                  <span className="text-sm font-medium text-navy-800">
                    {MOIS_LABEL[Number(m) - 1]} {annee}
                  </span>
                  <div className="flex flex-none items-center gap-2">
                    <div className="h-1.5 w-16 overflow-hidden rounded-full bg-navy-100 sm:w-24">
                      <div className={`h-full rounded-full ${taux >= 90 ? 'bg-green-500' : taux >= 75 ? 'bg-gold-500' : 'bg-red-500'}`} style={{ width: `${taux}%` }} />
                    </div>
                    <span className="w-10 text-right text-xs font-semibold tabular-nums text-navy-700">{taux}%</span>
                    <ChevronRight className="h-4 w-4 flex-none text-navy-300" />
                  </div>
                </button>
              )
            })}
          </div>
        </>
      )}

      {moisOuvert && <DetailMoisModal cle={moisOuvert} jours={parMois.get(moisOuvert) ?? []} onClose={() => setMoisOuvert(null)} />}
    </Card>
  )
}

const JOUR_STATUT: Record<JourAssiduite['statut'], { label: string; tone: 'green' | 'red' | 'gold' }> = {
  present: { label: 'Présent / Present', tone: 'green' },
  absent_justifiee: { label: 'Absence justifiée / Excused absence', tone: 'gold' },
  absent_non_justifiee: { label: 'Absence non justifiée / Unexcused absence', tone: 'red' },
}

function DetailMoisModal({ cle, jours, onClose }: { cle: string; jours: JourAssiduite[]; onClose: () => void }) {
  const [annee, m] = cle.split('-')
  const parDate = [...jours].sort((a, b) => b.date.localeCompare(a.date))

  return (
    <Modal title={`${MOIS_LABEL[Number(m) - 1]} ${annee} — ${tauxPresence(jours)}% de présence / attendance`} onClose={onClose}>
      <ul className="flex flex-col divide-y divide-navy-50">
        {parDate.map((j) => (
          <li key={j.date} className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
            <span className="text-sm font-medium text-navy-800">
              {new Date(`${j.date}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })}
            </span>
            <Badge tone={JOUR_STATUT[j.statut].tone}>{JOUR_STATUT[j.statut].label}</Badge>
          </li>
        ))}
      </ul>
    </Modal>
  )
}

function AssiduiteCard() {
  const { data: absences, isLoading } = useQuery({ queryKey: ['eleve-absences'], queryFn: fetchAbsences })
  const nonJustifiees = absences?.filter((a) => a.statut === 'absent' && !a.justifie).length ?? 0

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <CalendarX className="h-4 w-4" />
        Absences / Absences
      </h2>

      {isLoading ? (
        <Spinner />
      ) : !absences || absences.length === 0 ? (
        <p className="text-sm text-navy-400">Aucune absence relevée. / No absence recorded.</p>
      ) : (
        <>
          {nonJustifiees > 0 && (
            <p className="mb-3 rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
              {nonJustifiees} absence(s) non justifiée(s). / {nonJustifiees} unexcused absence(s).
            </p>
          )}
          <div className="flex flex-col divide-y divide-navy-50">
            {absences.map((a, i) => (
              <div key={i} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <span className="text-navy-700">
                  {new Date(a.date).toLocaleDateString('fr-FR')} — {a.statut === 'retard' ? 'Retard / Late' : 'Absence / Absence'}
                  {a.remarque ? ` · ${a.remarque}` : ''}
                </span>
                <Badge tone={a.justifie ? 'green' : 'red'}>{a.justifie ? 'Justifiée / Excused' : 'Non justifiée / Unexcused'}</Badge>
              </div>
            ))}
          </div>
        </>
      )}
    </Card>
  )
}

function EmploiDuTempsCard() {
  const { t } = useTranslation()
  const { data: creneaux, isLoading } = useQuery({ queryKey: ['eleve-emploi-du-temps'], queryFn: fetchEmploiDuTemps })

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <CalendarClock className="h-4 w-4" />
        Emploi du temps / Timetable
      </h2>
      {isLoading ? (
        <Spinner />
      ) : !creneaux || creneaux.length === 0 ? (
        <p className="text-sm text-navy-400">Aucun emploi du temps renseigné pour l'instant. / No timetable recorded yet.</p>
      ) : (
        <div className="flex flex-col divide-y divide-navy-50">
          {JOURS.filter((j) => creneaux.some((c) => c.jour === j.valeur)).map((j) => (
            <div key={j.valeur} className="py-2.5 first:pt-0 last:pb-0">
              <h3 className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-navy-400">
                {t(`emploiDuTemps.jours.${j.libelle}`)}
              </h3>
              <div className="flex flex-col gap-1.5">
                {creneaux
                  .filter((c) => c.jour === j.valeur)
                  .map((c) => (
                    <div key={c.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                      <span className="text-navy-700">
                        {c.heure_debut}–{c.heure_fin} · {c.matiere || '—'}
                        {c.salle ? ` · ${c.salle}` : ''}
                      </span>
                      <span className="text-xs text-navy-400">{c.enseignant || '—'}</span>
                    </div>
                  ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

function InfirmerieCard() {
  const { data: visites, isLoading } = useQuery({ queryKey: ['eleve-visites-infirmerie'], queryFn: fetchVisitesInfirmerie })

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <Stethoscope className="h-4 w-4 text-gold-500" />
        Passages à l'infirmerie / School nurse visits
      </h2>
      {isLoading ? (
        <Spinner />
      ) : !visites || visites.length === 0 ? (
        <p className="text-sm text-navy-400">Aucun passage à l'infirmerie relevé. / No school nurse visit recorded.</p>
      ) : (
        <div className="flex flex-col divide-y divide-navy-50">
          {visites.map((v) => (
            <div key={v.id} className="flex flex-col gap-1 py-2.5 first:pt-0 last:pb-0">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-sm font-semibold text-navy-800">{new Date(v.date_visite).toLocaleString('fr-FR')}</span>
                {v.raison && <Badge tone="gold">{v.raison}</Badge>}
              </div>
              {v.malaises.length > 0 && <p className="text-xs text-navy-500">{v.malaises.map((m) => m.label_fr).join(', ')}</p>}
              {v.soins_prodiges && <p className="text-sm text-navy-700">{v.soins_prodiges}</p>}
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

function DisciplineCard() {
  const { data: dossier, isLoading } = useQuery({ queryKey: ['eleve-sanctions'], queryFn: fetchSanctions })

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <Gavel className="h-4 w-4" />
        Discipline
      </h2>
      {isLoading ? (
        <Spinner />
      ) : !dossier || dossier.sanctions.length === 0 ? (
        <p className="text-sm text-navy-400">Rien à signaler. / Nothing to report.</p>
      ) : (
        <>
          {dossier.est_exclu && (
            <p className="mb-3 flex items-center gap-2 rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
              <ShieldAlert className="h-4 w-4 flex-none" />
              Exclusion en cours / Ongoing exclusion{dossier.motif_exclusion ? ` — ${dossier.motif_exclusion}` : ''}
            </p>
          )}
          <div className="flex flex-col divide-y divide-navy-50">
            {dossier.sanctions.map((s) => (
              <div key={s.id} className="flex flex-col gap-1 py-2.5 first:pt-0 last:pb-0">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="text-sm font-semibold text-navy-800">
                    {new Date(s.date_sanction).toLocaleDateString('fr-FR')} — {s.type}
                  </span>
                  <Badge tone={s.statut === 'confirmee' ? 'red' : 'gold'}>{s.statut}</Badge>
                </div>
                <p className="text-sm text-navy-700">{s.motif}</p>
              </div>
            ))}
          </div>
        </>
      )}
    </Card>
  )
}

