import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, Clock3, RefreshCw, RotateCcw, Save, Trash2 } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import {
    enregistrerRegleCalendrier,
    fetchCalendrierScolaire,
    fetchJourCalendrier,
    recalculerDatesPrevues,
    supprimerRegleCalendrier,
    type RegleCalendrier,
} from '@/features/progression/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { succes, erreur as alerteErreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const NOMS_JOURS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
const NOMS_MOIS = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
]

function pad(valeur: number): string {
    return String(valeur).padStart(2, '0')
}

function versISO(annee: number, moisZero: number, jour: number): string {
    return `${annee}-${pad(moisZero + 1)}-${pad(jour)}`
}

function ajouterJours(iso: string, delta: number): string {
    const [a, m, j] = iso.split('-').map(Number)
    const date = new Date(a, m - 1, j + delta)
    return versISO(date.getFullYear(), date.getMonth(), date.getDate())
}

/** Grille de semaines (lundi→dimanche) du mois donné, complétée par les jours voisins. */
function grilleMois(annee: number, moisZero: number): { iso: string; horsMois: boolean }[][] {
    const premierJour = new Date(annee, moisZero, 1)
    const decalage = (premierJour.getDay() + 6) % 7 // lundi = 0
    const nbJours = new Date(annee, moisZero + 1, 0).getDate()
    const debutGrille = ajouterJours(versISO(annee, moisZero, 1), -decalage)

    const cellules: { iso: string; horsMois: boolean }[] = []
    for (let i = 0; i < decalage; i++) cellules.push({ iso: ajouterJours(debutGrille, i), horsMois: true })
    for (let jour = 1; jour <= nbJours; jour++) cellules.push({ iso: versISO(annee, moisZero, jour), horsMois: false })
    while (cellules.length % 7 !== 0) cellules.push({ iso: ajouterJours(cellules[cellules.length - 1].iso, 1), horsMois: true })

    const semaines: { iso: string; horsMois: boolean }[][] = []
    for (let i = 0; i < cellules.length; i += 7) semaines.push(cellules.slice(i, i + 7))
    return semaines
}

function libelleJour(iso: string): string {
    const [a, m, j] = iso.split('-').map(Number)
    return new Date(a, m - 1, j).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

export function CalendrierScolairePage() {
    const queryClient = useQueryClient()
    const [date, setDate] = useState('')
    const [motif, setMotif] = useState('')
    const [portee, setPortee] = useState('ecole')
    const [cible, setCible] = useState('')
    const [ouvert, setOuvert] = useState(false)
    const [enregistrement, setEnregistrement] = useState(false)
    const [jourSelectionne, setJourSelectionne] = useState<string | null>(null)
    const aujourdhui = useMemo(() => { const d = new Date(); return versISO(d.getFullYear(), d.getMonth(), d.getDate()) }, [])
    const [moisCourant, setMoisCourant] = useState(() => { const d = new Date(); return { annee: d.getFullYear(), mois: d.getMonth() } })
    const [moisInitialise, setMoisInitialise] = useState(false)

    const { data, isLoading } = useQuery({ queryKey: ['calendrier-scolaire'], queryFn: fetchCalendrierScolaire })
    const { data: classes = [] } = useQuery({ queryKey: ['classes'], queryFn: fetchClasses })

    const niveaux = classes.filter((classe, index, liste) => classe.niveau_id && liste.findIndex((item) => item.niveau_id === classe.niveau_id) === index)
    const sousSystemes = classes.filter((classe, index, liste) => classe.sous_systeme_id && liste.findIndex((item) => item.sous_systeme_id === classe.sous_systeme_id) === index)

    // Recadre le mois affiché dans l'année scolaire active, une seule fois au chargement.
    useEffect(() => {
        if (!data || moisInitialise) return
        const courant = versISO(moisCourant.annee, moisCourant.mois, 15)
        if (courant < data.annee.date_debut) {
            const [a, m] = data.annee.date_debut.split('-').map(Number)
            setMoisCourant({ annee: a, mois: m - 1 })
        } else if (courant > data.annee.date_fin) {
            const [a, m] = data.annee.date_fin.split('-').map(Number)
            setMoisCourant({ annee: a, mois: m - 1 })
        }
        setMoisInitialise(true)
    }, [data, moisInitialise, moisCourant])

    const reglesParDate = useMemo(() => {
        const carte = new Map<string, RegleCalendrier[]>()
        for (const regle of data?.regles ?? []) {
            const liste = carte.get(regle.date) ?? []
            liste.push(regle)
            carte.set(regle.date, liste)
        }
        return carte
    }, [data])

    const cibleActuelle = (): { classe_id?: number; niveau_id?: number; sous_systeme_id?: number } => {
        if (portee === 'classe' && cible) return { classe_id: Number(cible) }
        if (portee === 'niveau' && cible) return { niveau_id: Number(cible) }
        if (portee === 'sous_systeme' && cible) return { sous_systeme_id: Number(cible) }
        return {}
    }

    const { data: detailJour, isLoading: detailEnCours } = useQuery({
        queryKey: ['calendrier-scolaire-jour', jourSelectionne, portee, cible],
        queryFn: () => fetchJourCalendrier(jourSelectionne as string, cibleActuelle()),
        enabled: !!jourSelectionne,
    })

    const recalcul = useMutation({
        mutationFn: recalculerDatesPrevues,
        onSuccess: (resultat) => {
            succes(resultat.modifiees > 0 ? `${resultat.modifiees} leçon(s) replanifiée(s) selon l'emploi du temps.` : 'Les dates prévues sont déjà à jour.')
            queryClient.invalidateQueries({ queryKey: ['progression-etablissement'] })
            queryClient.invalidateQueries({ queryKey: ['progression-classe'] })
            queryClient.invalidateQueries({ queryKey: ['calendrier-scolaire-jour'] })
        },
        onError: (error) => alerteErreur((error as ApiError).message),
    })

    const enregistrer = async () => {
        if (!date) return
        setEnregistrement(true)
        try {
            await enregistrerRegleCalendrier({
                date,
                est_ouvert: ouvert,
                motif: motif || null,
                classe_id: portee === 'classe' ? Number(cible) : null,
                niveau_id: portee === 'niveau' ? Number(cible) : null,
                sous_systeme_id: portee === 'sous_systeme' ? Number(cible) : null,
            })
            await queryClient.invalidateQueries({ queryKey: ['calendrier-scolaire'] })
            await queryClient.invalidateQueries({ queryKey: ['calendrier-scolaire-jour'] })
            succes(ouvert ? 'Jour rouvert et progression réajustée.' : 'Jour déclaré sans classe et progression réajustée.')
            setMotif('')
        } catch (error) {
            alerteErreur((error as ApiError).message)
        } finally {
            setEnregistrement(false)
        }
    }

    const supprimer = async (id: number) => {
        try {
            await supprimerRegleCalendrier(id)
            await queryClient.invalidateQueries({ queryKey: ['calendrier-scolaire'] })
            await queryClient.invalidateQueries({ queryKey: ['calendrier-scolaire-jour'] })
            succes('Règle supprimée : le jour est rouvert.')
        } catch (error) {
            alerteErreur((error as ApiError).message)
        }
    }

    const moisPeutReculer = !data || versISO(moisCourant.annee, moisCourant.mois, 1) > data.annee.date_debut
    const moisPeutAvancer = !data || versISO(moisCourant.annee, moisCourant.mois, 28) < data.annee.date_fin
    const changerMois = (delta: number) => {
        const total = moisCourant.annee * 12 + moisCourant.mois + delta
        setMoisCourant({ annee: Math.floor(total / 12), mois: ((total % 12) + 12) % 12 })
    }

    const semaines = grilleMois(moisCourant.annee, moisCourant.mois)

    return (
        <div className="flex flex-col gap-5">
            <PageHeader
                titre="Calendrier annuel"
                sousTitre="Jours ouverts et jours sans classe pour la progression pédagogique."
                icon={CalendarDays}
                actions={
                    <Button type="button" variant="secondary" onClick={() => recalcul.mutate()} disabled={recalcul.isPending} title="Recalcule les dates prévues des leçons à partir de l’emploi du temps et du calendrier">
                        <RefreshCw className={`h-4 w-4 ${recalcul.isPending ? 'animate-spin' : ''}`} />
                        {recalcul.isPending ? 'Recalcul…' : 'Recalculer les dates prévues'}
                    </Button>
                }
            />
            {isLoading ? <Spinner /> : (
                <>
                    <section className="grid gap-4 rounded-2xl border border-navy-100 bg-white p-5 shadow-soft lg:grid-cols-[1.2fr_1fr_1fr_1.2fr_auto] lg:items-end">
                        <label className="flex flex-col gap-1.5 text-sm font-semibold text-navy-700">Date
                            <input type="date" min={data?.annee.date_debut} max={data?.annee.date_fin} value={date} onChange={(event) => setDate(event.target.value)} className="rounded-xl border border-navy-200 px-3 py-2.5 font-normal text-navy-800 focus:border-navy-400 focus:outline-none" />
                        </label>
                        <label className="flex flex-col gap-1.5 text-sm font-semibold text-navy-700">Portée
                            <select value={portee} onChange={(event) => { setPortee(event.target.value); setCible('') }} className="rounded-xl border border-navy-200 bg-white px-3 py-2.5 font-normal text-navy-800 focus:border-navy-400 focus:outline-none">
                                <option value="ecole">Toute l’école</option><option value="sous_systeme">Sous-système</option><option value="niveau">Niveau</option><option value="classe">Classe</option>
                            </select>
                        </label>
                        <label className="flex flex-col gap-1.5 text-sm font-semibold text-navy-700">Cible
                            <select disabled={portee === 'ecole'} value={cible} onChange={(event) => setCible(event.target.value)} className="rounded-xl border border-navy-200 bg-white px-3 py-2.5 font-normal text-navy-800 disabled:bg-navy-50 focus:border-navy-400 focus:outline-none">
                                <option value="">Choisir…</option>
                                {portee === 'classe' && classes.map((classe) => <option key={classe.id} value={classe.id}>{classe.nom}</option>)}
                                {portee === 'niveau' && niveaux.map((classe) => <option key={classe.niveau_id} value={classe.niveau_id}>{classe.niveau?.name_fr ?? classe.niveau?.code}</option>)}
                                {portee === 'sous_systeme' && sousSystemes.map((classe) => <option key={classe.sous_systeme_id} value={classe.sous_systeme_id!}>{classe.sous_systeme?.nom}</option>)}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1.5 text-sm font-semibold text-navy-700">Motif
                            <input value={motif} onChange={(event) => setMotif(event.target.value)} placeholder="Fête, examen, fermeture…" className="rounded-xl border border-navy-200 px-3 py-2.5 font-normal text-navy-800 focus:border-navy-400 focus:outline-none" />
                        </label>
                        <div className="flex gap-2">
                            <Button type="button" variant={ouvert ? 'secondary' : 'danger'} onClick={enregistrer} disabled={!date || (portee !== 'ecole' && !cible) || enregistrement}><Save className="h-4 w-4" />{ouvert ? 'Rouvrir' : 'Fermer'}</Button>
                            <Button type="button" variant="ghost" onClick={() => setOuvert(!ouvert)} title="Basculer entre fermeture et réouverture"><RotateCcw className="h-4 w-4" /></Button>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-navy-100 bg-white p-5 shadow-soft">
                        <div className="mb-4 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Button type="button" variant="ghost" size="sm" onClick={() => changerMois(-1)} disabled={!moisPeutReculer} title="Mois précédent"><ChevronLeft className="h-4 w-4" /></Button>
                                <h2 className="w-48 text-center font-display text-lg font-bold text-navy-900">{NOMS_MOIS[moisCourant.mois]} {moisCourant.annee}</h2>
                                <Button type="button" variant="ghost" size="sm" onClick={() => changerMois(1)} disabled={!moisPeutAvancer} title="Mois suivant"><ChevronRight className="h-4 w-4" /></Button>
                            </div>
                            <span className="text-sm text-navy-400">{data?.annee.libelle} — cliquez un jour pour voir cours, leçons et enseignants</span>
                        </div>
                        <div className="grid grid-cols-7 gap-1.5 text-center text-xs font-bold uppercase tracking-wide text-navy-400">
                            {NOMS_JOURS.map((nom) => <div key={nom} className="py-1">{nom}</div>)}
                        </div>
                        <div className="mt-1 grid grid-cols-7 gap-1.5">
                            {semaines.flat().map(({ iso, horsMois }) => {
                                const horsPeriode = !data || iso < data.annee.date_debut || iso > data.annee.date_fin
                                const regles = reglesParDate.get(iso) ?? []
                                const aUneFermeture = regles.some((r) => !r.est_ouvert)
                                const aUneReouverture = regles.some((r) => r.est_ouvert)
                                const jourDuMois = Number(iso.split('-')[2])
                                return (
                                    <button
                                        key={iso}
                                        type="button"
                                        disabled={horsPeriode}
                                        onClick={() => setJourSelectionne(iso)}
                                        className={`relative flex h-16 flex-col items-center justify-center gap-1 rounded-xl border text-sm transition-colors ${horsPeriode ? 'cursor-not-allowed border-transparent text-navy-200' : 'border-navy-100 hover:border-gold-300 hover:bg-gold-50/50'} ${horsMois && !horsPeriode ? 'text-navy-300' : ''} ${iso === aujourdhui ? 'ring-2 ring-navy-400' : ''}`}
                                    >
                                        <span className={`font-semibold ${horsMois && !horsPeriode ? 'text-navy-300' : 'text-navy-800'}`}>{jourDuMois}</span>
                                        {(aUneFermeture || aUneReouverture) && (
                                            <span className={`h-1.5 w-1.5 rounded-full ${aUneFermeture ? 'bg-red-500' : 'bg-emerald-500'}`} />
                                        )}
                                    </button>
                                )
                            })}
                        </div>
                        <div className="mt-4 flex flex-wrap gap-4 text-xs text-navy-500">
                            <span className="flex items-center gap-1.5"><span className="h-1.5 w-1.5 rounded-full bg-red-500" /> Fermeture (jour ou classe sans cours)</span>
                            <span className="flex items-center gap-1.5"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Réouverture exceptionnelle</span>
                            <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full ring-2 ring-navy-400" /> Aujourd’hui</span>
                        </div>
                    </section>

                    <div className="flex items-center justify-between"><h2 className="font-display text-lg font-bold text-navy-900">Exceptions déclarées</h2><span className="text-sm text-navy-400">Les changements recalculent les dates prévues.</span></div>
                    {data?.regles.length ? <div className="overflow-hidden rounded-2xl border border-navy-100 bg-white shadow-soft"><table className="w-full text-left text-sm"><thead className="bg-navy-50 text-xs uppercase tracking-wide text-navy-500"><tr><th className="px-4 py-3">Date</th><th className="px-4 py-3">Portée</th><th className="px-4 py-3">État</th><th className="px-4 py-3">Motif</th><th className="px-4 py-3" /></tr></thead><tbody className="divide-y divide-navy-100">{data.regles.map((regle) => <tr key={regle.id}><td className="px-4 py-3 font-semibold text-navy-800">{regle.date}</td><td className="px-4 py-3 text-navy-600">{regle.classe ?? regle.niveau ?? regle.sous_systeme ?? 'Toute l’école'}</td><td className="px-4 py-3">{regle.est_ouvert ? <span className="text-emerald-600">Ouvert</span> : <span className="text-red-600">Sans classe</span>}</td><td className="px-4 py-3 text-navy-500">{regle.motif ?? '—'}</td><td className="px-4 py-3 text-right"><Button variant="ghost" size="sm" onClick={() => supprimer(regle.id)} title="Supprimer la règle"><Trash2 className="h-4 w-4 text-red-500" /></Button></td></tr>)}</tbody></table></div> : <EmptyState label="Aucune exception : les créneaux de l’emploi du temps sont ouverts." />}
                </>
            )}

            {jourSelectionne && (
                <Modal title={libelleJour(jourSelectionne)} onClose={() => setJourSelectionne(null)} taille="lg">
                    {detailEnCours ? <Spinner /> : detailJour ? (
                        <div className="flex flex-col gap-5">
                            {detailJour.classes_fermees.length > 0 && (
                                <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                    Jour sans classe pour : {detailJour.classes_fermees.join(', ')}
                                </div>
                            )}

                            <div>
                                <h3 className="mb-2 flex items-center gap-2 font-display text-sm font-bold text-navy-800"><Clock3 className="h-4 w-4 text-navy-400" /> Cours prévus ({detailJour.cours.length})</h3>
                                {detailJour.cours.length ? (
                                    <ul className="flex flex-col gap-2">
                                        {detailJour.cours.map((c) => (
                                            <li key={c.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-navy-100 px-3 py-2 text-sm">
                                                <span className="font-semibold text-navy-800">{c.heure_debut}–{c.heure_fin}</span>
                                                <span className="text-navy-600">{c.classe}</span>
                                                <span className="text-navy-500">{c.matiere ?? '—'}</span>
                                                <span className="ml-auto text-navy-400">{c.enseignant ?? 'Enseignant non assigné'}</span>
                                                {c.salle && <span className="rounded-lg bg-navy-50 px-2 py-0.5 text-xs text-navy-500">{c.salle}</span>}
                                            </li>
                                        ))}
                                    </ul>
                                ) : <EmptyState label="Aucun créneau ce jour-là." />}
                            </div>

                            <div>
                                <h3 className="mb-2 flex items-center gap-2 font-display text-sm font-bold text-navy-800"><CheckCircle2 className="h-4 w-4 text-navy-400" /> Leçons prévues ({detailJour.lecons.length})</h3>
                                {detailJour.lecons.length ? (
                                    <ul className="flex flex-col gap-2">
                                        {detailJour.lecons.map((l) => (
                                            <li key={l.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-navy-100 px-3 py-2 text-sm">
                                                <span className="font-semibold text-navy-800">{l.titre}</span>
                                                <span className="text-navy-600">{l.classe}</span>
                                                <span className="text-navy-500">{l.matiere ?? '—'}</span>
                                                <span className="ml-auto text-navy-400">{l.enseignant ?? 'Enseignant non assigné'}</span>
                                                {l.date_realisee && <span className="rounded-lg bg-emerald-50 px-2 py-0.5 text-xs text-emerald-600">Déjà traitée</span>}
                                            </li>
                                        ))}
                                    </ul>
                                ) : <EmptyState label="Aucune leçon prévue ce jour-là." />}
                            </div>

                            <div className="flex justify-end border-t border-navy-50 pt-4">
                                <Button type="button" variant="secondary" onClick={() => { setDate(jourSelectionne); setJourSelectionne(null) }}>
                                    Utiliser cette date pour une exception
                                </Button>
                            </div>
                        </div>
                    ) : <EmptyState label="Impossible de charger ce jour." />}
                </Modal>
            )}
        </div>
    )
}
