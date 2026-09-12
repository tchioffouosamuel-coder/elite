import { useState } from 'react'
import { Check, X } from 'lucide-react'
import { creerTroncCommunGroupe, type ClasseEnseignantMatiere } from '@/features/pedagogie/api'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'

export interface PropositionTroncCommun {
    id: string
    matiereId: number
    matiere: string
    personnelId: number
    enseignant: string
    classes: ClasseEnseignantMatiere[]
}

export function TroncCommunApresAffectationModal({
    propositions,
    onClose,
    onDone,
}: {
    propositions: PropositionTroncCommun[]
    onClose: () => void
    onDone: () => void
}) {
    const [noms, setNoms] = useState<Record<string, string>>(() => Object.fromEntries(
        propositions.map((proposition) => [proposition.id, `${proposition.matiere} - ${proposition.enseignant}`]),
    ))
    const [traitement, setTraitement] = useState(false)
    const [decisions, setDecisions] = useState<Record<string, boolean>>({})

    const enregistrer = async (proposition: PropositionTroncCommun) => {
        const nom = noms[proposition.id]?.trim()
        if (!nom) return

        await creerTroncCommunGroupe({
            matiere_id: proposition.matiereId,
            personnel_id: proposition.personnelId,
            nom,
            classe_ids: proposition.classes.flatMap((classe) => classe.classe ? [classe.classe.id] : []),
        })
    }

    const traiterTout = async (oui: boolean) => {
        if (!oui) {
            onClose()
            return
        }

        setTraitement(true)
        try {
            await Promise.all(propositions.map(enregistrer))
            succes(`${propositions.length} groupe(s) ajouté(s) au tronc commun.`)
            onDone()
        } catch (err) {
            erreur((err as ApiError).message)
        } finally {
            setTraitement(false)
        }
    }

    const traiterUn = async (proposition: PropositionTroncCommun, oui: boolean) => {
        if (!oui) {
            setDecisions((courantes) => ({ ...courantes, [proposition.id]: false }))
            return
        }

        setTraitement(true)
        try {
            await enregistrer(proposition)
            setDecisions((courantes) => ({ ...courantes, [proposition.id]: true }))
            succes(`« ${proposition.matiere} » ajouté au tronc commun.`)
        } catch (err) {
            erreur((err as ApiError).message)
        } finally {
            setTraitement(false)
        }
    }

    return (
        <Modal title="Ajouter au tronc commun ?" onClose={onClose}>
            <div className="flex max-h-[70vh] flex-col gap-4 overflow-y-auto">
                <p className="text-sm text-navy-600">
                    Ces affectations ont la même matière et le même enseignant dans plusieurs classes. Voulez-vous les regrouper pour que les prochains créneaux soient automatiquement en tronc commun ?
                </p>

                {propositions.map((proposition) => (
                    <div key={proposition.id} className="rounded-xl border border-navy-100 bg-cream-50 p-3">
                        <div className="mb-2 flex items-start justify-between gap-3">
                            <div>
                                <p className="font-semibold text-navy-900">{proposition.matiere}</p>
                                <p className="text-xs text-navy-500">Enseignant : {proposition.enseignant}</p>
                                <p className="mt-1 text-xs text-navy-500">Classes : {proposition.classes.map((classe) => classe.classe?.nom ?? '—').join(' · ')}</p>
                            </div>
                            {decisions[proposition.id] !== undefined && (
                                decisions[proposition.id]
                                    ? <Check className="h-5 w-5 text-green-600" />
                                    : <X className="h-5 w-5 text-navy-400" />
                            )}
                        </div>
                        <Input label="Nom du groupe" value={noms[proposition.id] ?? ''} onChange={(event) => setNoms((courants) => ({ ...courants, [proposition.id]: event.target.value }))} />
                        <div className="mt-3 flex justify-end gap-2">
                            <Button size="sm" variant="secondary" disabled={traitement || decisions[proposition.id] !== undefined} onClick={() => void traiterUn(proposition, false)}>Non</Button>
                            <Button size="sm" disabled={traitement || decisions[proposition.id] !== undefined} onClick={() => void traiterUn(proposition, true)}>Oui</Button>
                        </div>
                    </div>
                ))}

                <div className="flex flex-wrap justify-end gap-2 border-t border-navy-100 pt-3">
                    <Button type="button" variant="secondary" disabled={traitement} onClick={() => void traiterTout(false)}>Non pour tous</Button>
                    <Button type="button" disabled={traitement} onClick={() => void traiterTout(true)}>Oui pour tous</Button>
                </div>
            </div>
        </Modal>
    )
}
