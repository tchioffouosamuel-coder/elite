import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { BookOpen, Plus, Trash2, X } from 'lucide-react'
import { fetchDepartements } from '@/features/personnel/api'
import { fetchMatieres, updateMatiere, type Matiere } from '@/features/pedagogie/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { Card } from '@/shared/ui/Card'

type MatiereAvecDept = Matiere

export function AssignMatieresDepartementPage() {
    const can = useAuthStore((s) => s.can)
    const queryClient = useQueryClient()

    const { data: departements } = useQuery({
        queryKey: ['departements'],
        queryFn: () => fetchDepartements(),
    })
    const { data: matieres, isLoading, isError } = useQuery({
        queryKey: ['matieres'],
        queryFn: () => fetchMatieres(),
    })

    const [selectedMatiere, setSelectedMatiere] = useState<number | null>(null)
    const [selectedDept, setSelectedDept] = useState<number | null>(null)
    const [submitting, setSubmitting] = useState(false)
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

    const handleAssign = async () => {
        if (!selectedMatiere || !selectedDept) return

        setSubmitting(true)
        try {
            const matiere = matieres?.find((m) => m.id === selectedMatiere)
            if (!matiere) return

            await updateMatiere(selectedMatiere, {
                nom: matiere.nom,
                nom_en: matiere.nom_en,
                departement_id: selectedDept
            })
            queryClient.invalidateQueries({ queryKey: ['matieres'] })
            queryClient.invalidateQueries({ queryKey: ['departements'] })
            setMessage({ type: 'success', text: 'Matière assignée au département' })
            setTimeout(() => setMessage(null), 3000)
            setSelectedMatiere(null)
            setSelectedDept(null)
        } catch (err) {
            setMessage({ type: 'error', text: 'Erreur lors de l\'assignation' })
        } finally {
            setSubmitting(false)
        }
    }

    const handleUnassign = async (matiereId: number) => {
        const matiere = matieres?.find((m) => m.id === matiereId)
        if (!matiere) return

        setSubmitting(true)
        try {
            await updateMatiere(matiereId, {
                nom: matiere.nom,
                nom_en: matiere.nom_en,
                departement_id: null
            })
            queryClient.invalidateQueries({ queryKey: ['matieres'] })
            queryClient.invalidateQueries({ queryKey: ['departements'] })
            setMessage({ type: 'success', text: 'Matière retirée du département' })
            setTimeout(() => setMessage(null), 3000)
        } catch (err) {
            setMessage({ type: 'error', text: 'Erreur lors du retrait' })
        } finally {
            setSubmitting(false)
        }
    }

    const colonnes: Colonne<MatiereAvecDept>[] = [
        {
            cle: 'nom',
            entete: 'Matière',
            valeur: (m) => m.nom,
            cellule: (m) => (
                <div>
                    <p className="font-semibold text-navy-900">{m.nom}</p>
                    {m.abbreviation && <p className="text-xs text-navy-500">{m.abbreviation}</p>}
                </div>
            ),
        },
        {
            cle: 'departement',
            entete: 'Département',
            valeur: (m) => m.departement?.nom || '—',
            cellule: (m) => (
                <span className="text-navy-700">{m.departement?.nom || '—'}</span>
            ),
        },
        {
            cle: 'actions',
            entete: 'Actions',
            cellule: (m) => (
                m.departement && can('pedagogie.manage') ? (
                    <button
                        onClick={() => handleUnassign(m.id)}
                        disabled={submitting}
                        className="flex items-center gap-1 rounded px-2 py-1 text-sm text-red-600 hover:bg-red-50 disabled:opacity-50"
                    >
                        <Trash2 className="h-4 w-4" />
                        Retirer
                    </button>
                ) : null
            ),
        },
    ]

    if (isLoading) return <Spinner />
    if (isError || !matieres) return <ErrorState />

    const matieresSansDept = matieres.filter((m) => !m.departement)

    return (
        <div className="flex flex-col gap-6">
            <PageHeader titre="Assigner les matières aux départements" icon={BookOpen} />

            {message && (
                <div className={`rounded-lg p-4 flex items-center justify-between ${message.type === 'success'
                        ? 'bg-green-50 text-green-800 border border-green-200'
                        : 'bg-red-50 text-red-800 border border-red-200'
                    }`}>
                    <span>{message.text}</span>
                    <button onClick={() => setMessage(null)} className="ml-4">
                        <X className="h-4 w-4" />
                    </button>
                </div>
            )}

            {can('pedagogie.manage') && (
                <Card>
                    <div className="flex flex-col gap-4">
                        <h2 className="text-lg font-semibold text-navy-900">Assigner une matière</h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Select
                                value={selectedMatiere?.toString() || ''}
                                onChange={(e) => setSelectedMatiere(e.target.value ? parseInt(e.target.value) : null)}
                                label="Matière"
                            >
                                <option value="">Sélectionner une matière</option>
                                {matieresSansDept.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.nom}
                                    </option>
                                ))}
                            </Select>
                            <Select
                                value={selectedDept?.toString() || ''}
                                onChange={(e) => setSelectedDept(e.target.value ? parseInt(e.target.value) : null)}
                                label="Département"
                            >
                                <option value="">Sélectionner un département</option>
                                {departements?.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.nom}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="flex justify-end">
                            <Button
                                onClick={handleAssign}
                                disabled={submitting || !selectedMatiere || !selectedDept}
                            >
                                <Plus className="h-4 w-4" />
                                Assigner
                            </Button>
                        </div>
                    </div>
                </Card>
            )}

            <div className="flex flex-col gap-3">
                <h2 className="text-lg font-semibold text-navy-900">Matières assignées</h2>
                <DataTable
                    colonnes={colonnes}
                    lignes={matieres.filter((m) => m.departement)}
                    cleLigne={(m) => m.id}
                    placeholderRecherche="Rechercher une matière…"
                    messageVide="Aucune matière assignée à un département"
                    largeurMin={320}
                />
            </div>

            <div className="flex flex-col gap-3">
                <h2 className="text-lg font-semibold text-navy-900">Matières sans département</h2>
                <DataTable
                    colonnes={colonnes}
                    lignes={matieresSansDept}
                    cleLigne={(m) => m.id}
                    placeholderRecherche="Rechercher une matière…"
                    messageVide="Toutes les matières sont assignées à un département"
                    largeurMin={320}
                />
            </div>
        </div>
    )
}
