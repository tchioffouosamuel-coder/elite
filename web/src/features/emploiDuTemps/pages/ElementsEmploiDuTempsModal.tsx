import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { ChevronDown, ChevronRight, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchClasses, type Classe } from '@/features/classes/api'
import {
    appliquerEmploiDuTempsElement,
    createEmploiDuTempsElement,
    deleteEmploiDuTempsElement,
    fetchEmploiDuTempsElements,
    updateEmploiDuTempsElement,
    JOURS,
    type CreneauIgnore,
    type EmploiDuTempsElement,
} from '@/features/emploiDuTemps/api'
import { confirmer, erreur, info, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'

const FORMULAIRE_VIDE = {
    type: 'pause' as 'pause' | 'activite',
    nom: '',
    heure_debut: '10:00',
    heure_fin: '10:15',
    jours: [1, 2, 3, 4, 5, 6],
    classe_ids: [] as number[],
}

type Formulaire = typeof FORMULAIRE_VIDE

export function ElementsEmploiDuTempsModal({ onClose, onChanged }: { onClose: () => void; onChanged: () => void }) {
    const queryClient = useQueryClient()
    const [form, setForm] = useState<Formulaire>(FORMULAIRE_VIDE)
    const [edition, setEdition] = useState<EmploiDuTempsElement | null>(null)
    const [conflits, setConflits] = useState<CreneauIgnore[]>([])
    const { data: elements = [], isLoading } = useQuery({ queryKey: ['emploi-du-temps-elements'], queryFn: fetchEmploiDuTempsElements })
    const { data: classes = [] } = useQuery({ queryKey: ['classes'], queryFn: fetchClasses })
    const arbreClasses = useArbreClasses(classes)

    const enregistrer = useMutation({
        mutationFn: () => edition
            ? updateEmploiDuTempsElement(edition.id, form)
            : createEmploiDuTempsElement(form),
        onSuccess: (resultat) => {
            queryClient.invalidateQueries({ queryKey: ['emploi-du-temps-elements'] })
            queryClient.invalidateQueries({ queryKey: ['emploi-du-temps'] })
            setConflits(resultat.ignores)
            if (resultat.ignores.length === 0) {
                succes(edition ? 'Élément mis à jour.' : 'Élément ajouté aux emplois du temps.')
            } else {
                info(`Enregistré, mais ${resultat.ignores.length} créneau(x) non généré(s) — voir le détail ci-dessous.`)
            }
            setEdition(null)
            setForm(FORMULAIRE_VIDE)
            onChanged()
        },
        onError: (err: ApiError) => erreur(err.message),
    })

    const supprimer = async (element: EmploiDuTempsElement) => {
        if (!await confirmer({ titre: `Supprimer ${element.nom} ?`, message: 'Les créneaux générés à partir de cet élément seront supprimés.', action: 'Supprimer' })) return
        try {
            await deleteEmploiDuTempsElement(element.id)
            queryClient.invalidateQueries({ queryKey: ['emploi-du-temps-elements'] })
            queryClient.invalidateQueries({ queryKey: ['emploi-du-temps'] })
            succes('Élément supprimé.')
            onChanged()
        } catch (err) {
            erreur((err as ApiError).message)
        }
    }

    const appliquer = async (element: EmploiDuTempsElement) => {
        try {
            const result = await appliquerEmploiDuTempsElement(element.id)
            queryClient.invalidateQueries({ queryKey: ['emploi-du-temps'] })
            setConflits(result.ignores)
            if (result.ignores.length === 0) {
                succes(`${result.creees} créneau(x) ajouté(s).`)
            } else {
                info(`${result.creees} créneau(x) ajouté(s), ${result.ignores.length} ignoré(s) — voir le détail ci-dessous.`)
            }
            onChanged()
        } catch (err) {
            erreur((err as ApiError).message)
        }
    }

    const commencerEdition = (element: EmploiDuTempsElement) => {
        setEdition(element)
        setForm({ type: element.type, nom: element.nom, heure_debut: element.heure_debut, heure_fin: element.heure_fin, jours: element.jours, classe_ids: element.classes.map((classe) => classe.id) })
    }

    const basculerJour = (jour: number) => setForm((courant) => ({ ...courant, jours: courant.jours.includes(jour) ? courant.jours.filter((item) => item !== jour) : [...courant.jours, jour].sort() }))
    const basculerClasse = (id: number) => setForm((courant) => ({ ...courant, classe_ids: courant.classe_ids.includes(id) ? courant.classe_ids.filter((item) => item !== id) : [...courant.classe_ids, id] }))
    // Coche tout le groupe s'il n'est pas déjà entièrement sélectionné, sinon le décoche entièrement.
    const basculerGroupe = (ids: number[]) => setForm((courant) => {
        const toutesCochees = ids.every((id) => courant.classe_ids.includes(id))
        return {
            ...courant,
            classe_ids: toutesCochees
                ? courant.classe_ids.filter((id) => !ids.includes(id))
                : [...courant.classe_ids, ...ids.filter((id) => !courant.classe_ids.includes(id))],
        }
    })

    return (
        <Modal title="Pauses et activités" onClose={onClose}>
            <div className="flex max-h-[75vh] flex-col gap-4 overflow-y-auto pr-1">
                <div className="rounded-xl border border-navy-100 bg-cream-50 p-3">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <h2 className="text-sm font-bold text-navy-800">{edition ? 'Modifier un élément' : 'Ajouter une pause ou une activité'}</h2>
                        {edition && <Button size="sm" variant="ghost" onClick={() => { setEdition(null); setForm(FORMULAIRE_VIDE) }}>Annuler</Button>}
                    </div>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Select label="Type" value={form.type} onChange={(event) => setForm({ ...form, type: event.target.value as Formulaire['type'] })}>
                            <option value="pause">Pause</option>
                            <option value="activite">Activité</option>
                        </Select>
                        <Input label="Nom" value={form.nom} onChange={(event) => setForm({ ...form, nom: event.target.value })} placeholder="Récréation, rassemblement…" />
                        <Input label="Heure de début" type="time" value={form.heure_debut} onChange={(event) => setForm({ ...form, heure_debut: event.target.value })} />
                        <Input label="Heure de fin" type="time" value={form.heure_fin} onChange={(event) => setForm({ ...form, heure_fin: event.target.value })} />
                    </div>
                    <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-navy-500">Jours par défaut</p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {JOURS.map((jour) => (
                            <label key={jour.valeur} className="flex items-center gap-1.5 text-sm text-navy-700">
                                <input type="checkbox" checked={form.jours.includes(jour.valeur)} onChange={() => basculerJour(jour.valeur)} className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500" />
                                {jour.libelle.slice(0, 3)}
                            </label>
                        ))}
                    </div>
                    <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-navy-500">Classes par défaut</p>
                    <div className="mt-2 flex max-h-64 flex-col gap-0.5 overflow-y-auto rounded-lg border border-navy-100 p-2">
                        {arbreClasses.map((ecole) => (
                            <GroupeClasses
                                key={ecole.id}
                                niveau={0}
                                titre={ecole.nom}
                                classeIds={ecole.classeIds}
                                cochees={form.classe_ids}
                                onBasculerGroupe={basculerGroupe}
                            >
                                {ecole.sousSystemes.map((sousSysteme) => (
                                    <GroupeClasses
                                        key={sousSysteme.id}
                                        niveau={1}
                                        titre={sousSysteme.nom}
                                        classeIds={sousSysteme.classeIds}
                                        cochees={form.classe_ids}
                                        onBasculerGroupe={basculerGroupe}
                                    >
                                        {sousSysteme.niveaux.map((niveauItem) => (
                                            <GroupeClasses
                                                key={niveauItem.id}
                                                niveau={2}
                                                titre={niveauItem.nom}
                                                classeIds={niveauItem.classes.map((classe) => classe.id)}
                                                cochees={form.classe_ids}
                                                onBasculerGroupe={basculerGroupe}
                                            >
                                                {niveauItem.classes.map((classe) => (
                                                    <label key={classe.id} className="ml-4 flex items-center gap-1.5 border-l border-navy-100 py-1 pl-3 text-sm text-navy-700">
                                                        <input type="checkbox" checked={form.classe_ids.includes(classe.id)} onChange={() => basculerClasse(classe.id)} className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500" />
                                                        <span className="truncate">{classe.nom}</span>
                                                    </label>
                                                ))}
                                            </GroupeClasses>
                                        ))}
                                    </GroupeClasses>
                                ))}
                            </GroupeClasses>
                        ))}
                        {arbreClasses.length === 0 && <p className="py-2 text-sm text-navy-400">Aucune classe.</p>}
                    </div>
                    <div className="mt-3 flex justify-end">
                        <Button type="button" onClick={() => enregistrer.mutate()} disabled={enregistrer.isPending || !form.nom || form.jours.length === 0 || form.classe_ids.length === 0}>
                            <Plus className="h-4 w-4" />
                            {edition ? 'Enregistrer' : 'Ajouter'}
                        </Button>
                    </div>
                </div>

                {conflits.length > 0 && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <div className="mb-2 flex items-center justify-between gap-2">
                            <h3 className="text-sm font-bold text-amber-800">
                                {conflits.length} créneau(x) non généré(s) — chevauchement avec un cours existant
                            </h3>
                            <Button size="sm" variant="ghost" onClick={() => setConflits([])}>Masquer</Button>
                        </div>
                        <ul className="flex flex-col gap-1 text-xs text-amber-800">
                            {conflits.map((conflit, index) => (
                                <li key={index}>
                                    <span className="font-semibold">{conflit.classe}</span> — {conflit.jour_libelle} : en conflit avec {conflit.conflit}
                                </li>
                            ))}
                        </ul>
                        <p className="mt-2 text-xs text-amber-700">
                            Déplacez ou supprimez le cours en conflit, puis cliquez sur « Appliquer » (icône ↻) pour générer le créneau manquant.
                        </p>
                    </div>
                )}

                {isLoading ? <p className="text-sm text-navy-400">Chargement…</p> : elements.map((element) => (
                    <div key={element.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-navy-100 bg-white p-3">
                        <div>
                            <p className={`font-semibold ${element.type === 'pause' ? 'text-blue-700' : 'text-yellow-700'}`}>{element.nom}</p>
                            <p className="text-xs text-navy-500">{element.heure_debut}–{element.heure_fin} · {element.classes.length} classe(s)</p>
                        </div>
                        <div className="flex items-center gap-1">
                            <Button size="sm" variant="ghost" title="Appliquer aux emplois du temps" onClick={() => void appliquer(element)}><RefreshCw className="h-4 w-4" /></Button>
                            <Button size="sm" variant="ghost" title="Modifier" onClick={() => commencerEdition(element)}><Pencil className="h-4 w-4" /></Button>
                            <Button size="sm" variant="ghost" title="Supprimer" onClick={() => void supprimer(element)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                        </div>
                    </div>
                ))}
            </div>
        </Modal>
    )
}

interface NoeudNiveau { id: number; nom: string; classes: Classe[] }
interface NoeudSousSysteme { id: number; nom: string; classeIds: number[]; niveaux: NoeudNiveau[] }
interface NoeudEcole { id: number; nom: string; classeIds: number[]; sousSystemes: NoeudSousSysteme[] }

function grouperPar<T>(items: T[], cle: (item: T) => number | string): Map<number | string, T[]> {
    const carte = new Map<number | string, T[]>()
    for (const item of items) {
        const groupe = carte.get(cle(item))
        if (groupe) groupe.push(item)
        else carte.set(cle(item), [item])
    }
    return carte
}

/** École > sous-système > niveau > classe — hiérarchie utilisée par les dropdowns de sélection des classes. */
function useArbreClasses(classes: Classe[]): NoeudEcole[] {
    return useMemo(() => {
        const parEcole = grouperPar(classes, (c) => c.school_id ?? c.school?.id ?? 0)

        return Array.from(parEcole.entries())
            .map(([ecoleId, classesEcole]): NoeudEcole => {
                const parSousSysteme = grouperPar(classesEcole, (c) => c.sous_systeme_id ?? 0)
                const sousSystemes = Array.from(parSousSysteme.entries())
                    .map(([sousSystemeId, classesSousSysteme]): NoeudSousSysteme => {
                        const parNiveau = grouperPar(classesSousSysteme, (c) => c.niveau_id)
                        const niveaux = Array.from(parNiveau.entries())
                            .map(([niveauId, classesNiveau]): NoeudNiveau => ({
                                id: Number(niveauId),
                                nom: classesNiveau[0].niveau?.name_fr ?? 'Sans niveau',
                                classes: [...classesNiveau].sort((a, b) => a.nom.localeCompare(b.nom, 'fr', { numeric: true })),
                            }))
                            .sort((a, b) => a.nom.localeCompare(b.nom, 'fr', { numeric: true }))

                        return {
                            id: Number(sousSystemeId),
                            nom: classesSousSysteme[0].sous_systeme?.nom ?? 'Sans sous-système',
                            classeIds: classesSousSysteme.map((c) => c.id),
                            niveaux,
                        }
                    })
                    .sort((a, b) => a.nom.localeCompare(b.nom, 'fr'))

                return {
                    id: Number(ecoleId),
                    nom: classesEcole[0].school?.name ?? 'Sans école',
                    classeIds: classesEcole.map((c) => c.id),
                    sousSystemes,
                }
            })
            .sort((a, b) => a.nom.localeCompare(b.nom, 'fr'))
    }, [classes])
}

/**
 * Section repliable d'un niveau de la hiérarchie (école, sous-système ou
 * niveau), avec sa propre case « tout cocher/décocher » — cochée quand
 * toutes les classes du groupe sont sélectionnées, indéterminée si certaines
 * seulement le sont.
 */
function GroupeClasses({ niveau, titre, classeIds, cochees, onBasculerGroupe, children }: {
    niveau: 0 | 1 | 2
    titre: string
    classeIds: number[]
    cochees: number[]
    onBasculerGroupe: (ids: number[]) => void
    children: ReactNode
}) {
    const [ouvert, setOuvert] = useState(true)
    const caseRef = useRef<HTMLInputElement>(null)
    const nbCochees = classeIds.filter((id) => cochees.includes(id)).length
    const toutesCochees = classeIds.length > 0 && nbCochees === classeIds.length
    const partiel = nbCochees > 0 && !toutesCochees

    useEffect(() => {
        if (caseRef.current) caseRef.current.indeterminate = partiel
    }, [partiel])

    if (classeIds.length === 0) return null

    return (
        <div className={niveau > 0 ? 'ml-4 border-l border-navy-100 pl-3' : ''}>
            <div className="flex items-center gap-1.5 py-1">
                <button type="button" onClick={() => setOuvert((o) => !o)} className="text-navy-400 hover:text-navy-700">
                    {ouvert ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
                </button>
                <input
                    ref={caseRef}
                    type="checkbox"
                    checked={toutesCochees}
                    onChange={() => onBasculerGroupe(classeIds)}
                    className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
                />
                <button
                    type="button"
                    onClick={() => setOuvert((o) => !o)}
                    className={
                        niveau === 0
                            ? 'text-sm font-bold text-navy-800'
                            : niveau === 1
                                ? 'text-sm font-semibold text-navy-700'
                                : 'text-sm text-navy-600'
                    }
                >
                    {titre}
                </button>
                <span className="ml-auto text-xs text-navy-400">{nbCochees}/{classeIds.length}</span>
            </div>
            {ouvert && <div className="flex flex-col gap-0.5">{children}</div>}
        </div>
    )
}
