import { useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Archive, ArrowLeft, Eye, GitMerge, Pencil, RotateCcw, Trash2, UserRound } from 'lucide-react'
import { archiveEleve, deleteEleve, fetchEleves, reactivateEleve, type Eleve } from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { EmptyState, ErrorState, Spinner } from '@/shared/ui/Feedback'
import { PageHeader } from '@/shared/ui/PageHeader'

interface GroupeDoublon {
    cle: string
    nom: string
    ecole: string
    eleves: Eleve[]
}

function normaliserNom(nom: string): string {
    return nom
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .replace(/\s+/g, ' ')
        .toLocaleLowerCase('fr')
}

export function ElevesDoublonsPage() {
    const navigate = useNavigate()
    const can = useAuthStore((state) => state.can)
    const queryClient = useQueryClient()
    const { data, isLoading, isError } = useQuery({
        queryKey: ['eleves'],
        queryFn: () => fetchEleves({ per_page: 1000 }),
    })

    const groupes = useMemo<GroupeDoublon[]>(() => {
        const groupesParCle = new Map<string, Eleve[]>()

        for (const eleve of data?.items ?? []) {
            const cle = `${eleve.school_id ?? 0}:${normaliserNom(eleve.nom_complet)}`
            const groupe = groupesParCle.get(cle) ?? []
            groupe.push(eleve)
            groupesParCle.set(cle, groupe)
        }

        return Array.from(groupesParCle.entries())
            .filter(([, eleves]) => eleves.length > 1)
            .map(([cle, eleves]) => ({
                cle,
                nom: eleves[0].nom_complet,
                ecole: eleves[0].school?.name ?? 'École non renseignée',
                eleves,
            }))
            .sort((a, b) => a.nom.localeCompare(b.nom, 'fr'))
    }, [data?.items])

    const rafraichir = () => {
        queryClient.invalidateQueries({ queryKey: ['eleves'] })
    }

    const changerStatut = async (eleve: Eleve) => {
        try {
            if (eleve.statut === 'actif') {
                const confirme = await confirmer({
                    titre: `Archiver ${eleve.nom_complet} ?`,
                    message: 'Cette fiche ne sera plus considérée comme active.',
                    action: 'Archiver',
                })
                if (!confirme) return
                await archiveEleve(eleve.id)
                succes('Fiche élève archivée.')
            } else {
                await reactivateEleve(eleve.id)
                succes('Fiche élève réactivée.')
            }
            rafraichir()
        } catch (err) {
            erreur((err as ApiError).message)
        }
    }

    const supprimer = async (eleve: Eleve) => {
        const confirme = await confirmer({
            titre: `Supprimer ${eleve.nom_complet} ?`,
            message: 'Cette action est irréversible et supprimera la fiche sélectionnée.',
            action: 'Supprimer',
        })
        if (!confirme) return

        try {
            await deleteEleve(eleve.id)
            succes('Fiche élève supprimée.')
            rafraichir()
        } catch (err) {
            erreur((err as ApiError).message)
        }
    }

    if (!can('eleves.manage')) {
        return <ErrorState />
    }

    return (
        <div className="flex flex-col gap-5">
            <PageHeader
                titre="Gestion des doublons"
                sousTitre="Fiches élèves portant le même nom dans une même école. Vérifiez chaque fiche avant de l'archiver ou de la supprimer."
                icon={GitMerge}
                actions={
                    <Button type="button" variant="secondary" onClick={() => navigate('/eleves')}>
                        <ArrowLeft className="h-4 w-4" />
                        Retour aux élèves
                    </Button>
                }
            />

            {isLoading ? (
                <Spinner />
            ) : isError || !data ? (
                <ErrorState />
            ) : groupes.length === 0 ? (
                <Card>
                    <EmptyState label="Aucun doublon apparent n'a été trouvé." />
                </Card>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="flex items-center gap-2 text-sm text-navy-600">
                        <UserRound className="h-4 w-4" />
                        {groupes.length} groupe(s) de doublons, {groupes.reduce((total, groupe) => total + groupe.eleves.length, 0)} fiche(s) à vérifier.
                    </div>

                    {groupes.map((groupe) => (
                        <Card key={groupe.cle}>
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-navy-100 pb-3">
                                <div>
                                    <h2 className="font-bold text-navy-900">{groupe.nom}</h2>
                                    <p className="text-xs text-navy-500">{groupe.ecole}</p>
                                </div>
                                <Badge tone="red">{groupe.eleves.length} fiches</Badge>
                            </div>

                            <div className="flex flex-col divide-y divide-navy-100">
                                {groupe.eleves.map((eleve) => (
                                    <div key={eleve.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                        <div className="min-w-0">
                                            <p className="font-semibold text-navy-900">{eleve.nom_complet}</p>
                                            <p className="text-xs text-navy-500">
                                                Matricule : {eleve.matricule ?? '—'} · Né(e) le : {eleve.date_naissance ?? '—'} · Classe : {eleve.classe?.nom ?? 'Sans classe'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Badge tone={eleve.statut === 'actif' ? 'green' : 'neutral'}>{eleve.statut === 'actif' ? 'Actif' : eleve.statut}</Badge>
                                            <button type="button" title="Consulter" onClick={() => navigate(`/eleves/${eleve.id}`)} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700">
                                                <Eye className="h-4 w-4" />
                                            </button>
                                            <button type="button" title="Modifier" onClick={() => navigate(`/eleves/${eleve.id}/edit`)} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700">
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button type="button" title={eleve.statut === 'actif' ? 'Archiver' : 'Réactiver'} onClick={() => void changerStatut(eleve)} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700">
                                                {eleve.statut === 'actif' ? <Archive className="h-4 w-4" /> : <RotateCcw className="h-4 w-4" />}
                                            </button>
                                            <button type="button" title="Supprimer" onClick={() => void supprimer(eleve)} className="rounded-lg p-1.5 text-red-400 hover:bg-red-50 hover:text-red-700">
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    )
}
