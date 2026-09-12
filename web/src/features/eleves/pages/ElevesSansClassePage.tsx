import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, UserRound } from 'lucide-react'
import { changerClasseEleve, fetchEleves, type Eleve } from '@/features/eleves/api'
import { fetchClasses, type Classe } from '@/features/classes/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function ClasseSelect({ eleve, classes }: { eleve: Eleve; classes: Classe[] }) {
    const queryClient = useQueryClient()
    const classesDeLEcole = classes.filter((classe) => (classe.school_id ?? classe.school?.id) === eleve.school_id)

    return (
        <select
            defaultValue=""
            onClick={(event) => event.stopPropagation()}
            onChange={async (event) => {
                const classeId = Number(event.target.value)
                if (!classeId) return

                try {
                    await changerClasseEleve(eleve.id, classeId)
                    succes(`${eleve.nom_complet} a été affecté à la classe sélectionnée.`)
                    await queryClient.invalidateQueries({ queryKey: ['eleves', 'sans-classe'] })
                    queryClient.invalidateQueries({ queryKey: ['eleves'] })
                    queryClient.invalidateQueries({ queryKey: ['classes'] })
                } catch (err) {
                    erreur((err as ApiError).message)
                    event.target.value = ''
                }
            }}
            className="rounded-md border border-navy-200 bg-white px-2 py-1 text-sm focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
        >
            <option value="">Définir la classe…</option>
            {classesDeLEcole.map((classe) => (
                <option key={classe.id} value={classe.id}>
                    {classe.nom}
                </option>
            ))}
        </select>
    )
}

export function ElevesSansClassePage() {
    const navigate = useNavigate()
    const { data: classes = [], isLoading: classesLoading } = useQuery({
        queryKey: ['classes'],
        queryFn: () => fetchClasses(),
    })
    const { data, isLoading, isError } = useQuery({
        queryKey: ['eleves', 'sans-classe'],
        queryFn: () => fetchEleves({ per_page: 1000 }),
    })

    const elevesSansClasse = (data?.items ?? []).filter((eleve) => eleve.classe === null)

    const colonnes: Colonne<Eleve>[] = [
        {
            cle: 'matricule',
            entete: 'Matricule',
            valeur: (eleve) => eleve.matricule,
            cellule: (eleve) => <span className="font-mono text-xs">{eleve.matricule ?? '—'}</span>,
        },
        {
            cle: 'nom',
            entete: 'Élève',
            valeur: (eleve) => eleve.nom_complet,
            cellule: (eleve) => (
                <div className="flex flex-wrap items-center gap-2">
                    <span className={`font-semibold ${eleve.non_reinscrit_annee_active ? 'text-red-700' : 'text-navy-900'}`}>
                        {eleve.nom_complet}
                    </span>
                    <ClasseSelect eleve={eleve} classes={classes} />
                    {eleve.non_reinscrit_annee_active && <Badge tone="red">Non préinscrit</Badge>}
                </div>
            ),
        },
        {
            cle: 'sexe',
            entete: 'Sexe',
            valeur: (eleve) => eleve.sexe,
            cellule: (eleve) => <Badge tone={eleve.sexe === 'F' ? 'gold' : 'neutral'}>{eleve.sexe === 'F' ? 'Féminin' : 'Masculin'}</Badge>,
        },
        {
            cle: 'ecole',
            entete: 'École',
            valeur: (eleve) => eleve.school?.name,
            cellule: (eleve) => eleve.school?.name ?? '—',
        },
        {
            cle: 'tuteur',
            entete: 'Tuteur',
            valeur: (eleve) => eleve.tuteurs?.[0]?.nom_complet,
            cellule: (eleve) => eleve.tuteurs?.[0]?.nom_complet ?? '—',
        },
    ]

    return (
        <div className="flex flex-col gap-5">
            <PageHeader
                titre="Élèves sans classe"
                sousTitre="Élèves actuellement inscrits qui ne sont affectés à aucune classe."
                icon={UserRound}
                actions={
                    <Button type="button" variant="secondary" onClick={() => navigate('/eleves')}>
                        <ArrowLeft className="h-4 w-4" />
                        Retour aux élèves
                    </Button>
                }
            />

            {isLoading || classesLoading ? (
                <Spinner />
            ) : isError || !data ? (
                <ErrorState />
            ) : elevesSansClasse.length === 0 ? (
                <EmptyState label="Tous les élèves sont affectés à une classe." />
            ) : (
                <DataTable
                    colonnes={colonnes}
                    lignes={elevesSansClasse}
                    cleLigne={(eleve) => eleve.id}
                    onLigneClick={(eleve) => navigate(`/eleves/${eleve.id}`)}
                    placeholderRecherche="Rechercher un élève…"
                    messageVide="Aucun élève ne correspond à cette recherche."
                    largeurMin={760}
                />
            )}
        </div>
    )
}