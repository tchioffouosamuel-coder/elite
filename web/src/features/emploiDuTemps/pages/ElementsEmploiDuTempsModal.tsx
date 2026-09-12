import { useState } from 'react'
import { Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchClasses, type Classe } from '@/features/classes/api'
import {
    appliquerEmploiDuTempsElement,
    createEmploiDuTempsElement,
    deleteEmploiDuTempsElement,
    fetchEmploiDuTempsElements,
    updateEmploiDuTempsElement,
    JOURS,
    type EmploiDuTempsElement,
} from '@/features/emploiDuTemps/api'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
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
    const { data: elements = [], isLoading } = useQuery({ queryKey: ['emploi-du-temps-elements'], queryFn: fetchEmploiDuTempsElements })
    const { data: classes = [] } = useQuery({ queryKey: ['classes'], queryFn: fetchClasses })

    const enregistrer = useMutation({
        mutationFn: () => edition
            ? updateEmploiDuTempsElement(edition.id, form)
            : createEmploiDuTempsElement(form),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['emploi-du-temps-elements'] })
            queryClient.invalidateQueries({ queryKey: ['emploi-du-temps'] })
            succes(edition ? 'Élément mis à jour.' : 'Élément ajouté aux emplois du temps.')
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
            succes(`${result.creees} créneau(x) ajouté(s).`)
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
                    <div className="mt-2 grid max-h-32 grid-cols-1 gap-1 overflow-y-auto sm:grid-cols-2">
                        {classes.map((classe: Classe) => (
                            <label key={classe.id} className="flex items-center gap-1.5 text-sm text-navy-700">
                                <input type="checkbox" checked={form.classe_ids.includes(classe.id)} onChange={() => basculerClasse(classe.id)} className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500" />
                                <span className="truncate">{classe.nom}</span>
                            </label>
                        ))}
                    </div>
                    <div className="mt-3 flex justify-end">
                        <Button type="button" onClick={() => enregistrer.mutate()} disabled={enregistrer.isPending || !form.nom || form.jours.length === 0 || form.classe_ids.length === 0}>
                            <Plus className="h-4 w-4" />
                            {edition ? 'Enregistrer' : 'Ajouter'}
                        </Button>
                    </div>
                </div>

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
