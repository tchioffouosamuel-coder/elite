import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, ArrowLeft, GitMerge, Trash2, UserRound, WandSparkles } from 'lucide-react'
import {
    batchDeleteEleves,
    fetchDoublonsDetailles,
    fusionnerDoublon,
    traitementAutomatiqueDoublons,
    type GroupeDoublonDetaille,
    type MembreDoublon,
} from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { confirmer, confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { EmptyState, ErrorState, Spinner } from '@/shared/ui/Feedback'
import { PageHeader } from '@/shared/ui/PageHeader'

const formatMontant = (montant: number) => `${new Intl.NumberFormat('fr-FR').format(montant)} FCFA`

const URGENCE_BADGE: Record<1 | 2 | 3, { tone: 'red' | 'gold' | 'neutral'; label: string }> = {
    3: { tone: 'red', label: 'Argent des deux côtés' },
    2: { tone: 'gold', label: 'Actifs dans la même classe' },
    1: { tone: 'neutral', label: 'Inactifs' },
}

/**
 * Gestion des doublons — revue humaine, pas fusion automatique aveugle.
 *
 * Ni « qui a une classe » ni « qui a des versements » ne départage la plupart
 * des groupes en pratique : les deux exemplaires sont souvent actifs dans la
 * même classe, ou tous deux payés. Chaque groupe est donc affiché avec les
 * deux fiches côte à côte, trié par urgence (l'argent en double d'abord), et
 * c'est l'utilisateur qui choisit laquelle garder.
 */
export function ElevesDoublonsPage() {
    const navigate = useNavigate()
    const can = useAuthStore((state) => state.can)
    const queryClient = useQueryClient()
    const [enCours, setEnCours] = useState<string | null>(null)
    const [selection, setSelection] = useState<Set<number>>(new Set())
    const [suppressionEnCours, setSuppressionEnCours] = useState(false)

    const { data, isLoading, isError } = useQuery({
        queryKey: ['eleves', 'doublons-detailles'],
        queryFn: fetchDoublonsDetailles,
    })
    const certains = data?.certains ?? []
    const potentiels = data?.potentiels ?? []

    const rafraichir = () => {
        queryClient.invalidateQueries({ queryKey: ['eleves'] })
        queryClient.invalidateQueries({ queryKey: ['eleves', 'doublons-detailles'] })
    }

    const toggleSelection = (id: number) => {
        setSelection((courant) => {
            const copie = new Set(courant)
            copie.has(id) ? copie.delete(id) : copie.add(id)
            return copie
        })
    }

    /**
     * Suppression directe (pas de fusion) : pensée pour les fiches sans la
     * moindre trace réelle repérées à l'œil dans un groupe — quand l'auto-
     * détection les laisse de côté (ex. la fiche réellement inscrite n'a pas
     * la même date de naissance renseignée et n'apparaît donc dans aucun
     * groupe). Recompte affiché avant confirmation ; aucune sauvegarde
     * possible ensuite, `Eleve` n'a pas de suppression douce.
     */
    const supprimerSelection = async () => {
        if (selection.size === 0) return
        if (!(await confirmerSuppression(
            `${selection.size} fiche(s) élève`,
            'Ces fiches seront définitivement supprimées — action irréversible, sans sauvegarde possible. Ne cochez que des fiches sans historique réel (aucune classe, aucun versement, aucune note...).',
        ))) return

        setSuppressionEnCours(true)
        try {
            const { deleted } = await batchDeleteEleves(Array.from(selection))
            succes(`${deleted} fiche(s) supprimée(s).`)
            setSelection(new Set())
            rafraichir()
        } catch (err) {
            erreur((err as ApiError).message)
        } finally {
            setSuppressionEnCours(false)
        }
    }

    const traiterAutomatiquement = async () => {
        const confirme = await confirmer({
            titre: 'Fusionner les doublons certains ?',
            message: "Seuls les groupes où même nom, même école et même date de naissance désignent une seule fiche rattachée à une classe cette année seront fusionnés dans celle-ci (notes, présences, sanctions...). Une paire dont les dossiers de scolarité se chevauchent sur une même année est laissée de côté, pour éviter de risquer un paiement compté deux fois ou perdu.",
            action: 'Fusionner automatiquement',
        })
        if (!confirme) return

        try {
            const resultat = await traitementAutomatiqueDoublons()
            rafraichir()
            const details: string[] = []
            if (resultat.conflits.length > 0) details.push(`${resultat.conflits.length} en conflit financier à vérifier`)
            if (resultat.ambigus.length > 0) details.push(`${resultat.ambigus.length} restent à trancher ci-dessous`)
            succes(`${resultat.fusionnes} doublon(s) fusionné(s).${details.length > 0 ? ' ' + details.join(', ') + '.' : ''}`)
        } catch (err) {
            erreur((err as ApiError).message)
        }
    }

    const fusionner = async (groupe: GroupeDoublonDetaille, conservee: MembreDoublon) => {
        const autres = groupe.membres.filter((m) => m.id !== conservee.id)

        const confirme = await confirmer({
            titre: `Garder la fiche de ${conservee.matricule ?? conservee.id} ?`,
            message: `Notes, présences, sanctions, dossier scolaire... de ${autres.length > 1 ? 'toutes les autres fiches' : "l'autre fiche"} seront rattachés à celle-ci, qui sera seule conservée. Si un dossier de scolarité se chevauche sur une même année, la fusion de cette paire sera refusée plutôt que de risquer un paiement compté deux fois.`,
            action: 'Fusionner',
        })
        if (!confirme) return

        setEnCours(`${groupe.nom}:${conservee.id}`)
        try {
            let fusionnes = 0
            const refus: string[] = []
            for (const autre of autres) {
                const resultat = await fusionnerDoublon(conservee.id, autre.id)
                if (resultat.fusionne) fusionnes++
                else refus.push(autre.matricule ?? String(autre.id))
            }
            rafraichir()
            if (refus.length === 0) {
                succes(`${fusionnes} fiche(s) fusionnée(s) dans ${conservee.matricule ?? conservee.id}.`)
            } else {
                erreur(`${fusionnes} fusionnée(s), mais refusé pour ${refus.join(', ')} (dossiers de scolarité en chevauchement).`)
            }
        } catch (err) {
            erreur((err as ApiError).message)
        } finally {
            setEnCours(null)
        }
    }

    if (!can('eleves.manage')) {
        return <ErrorState />
    }

    const totalFiches = certains.reduce((total, g) => total + g.membres.length, 0)
    const totalVerse = certains.reduce((total, g) => total + g.membres.reduce((s, m) => s + m.total_versements, 0), 0)

    const renderGroupe = (groupe: GroupeDoublonDetaille) => {
        const badge = URGENCE_BADGE[groupe.urgence]
        const cle = groupe.potentiel ? 'p:' : 'c:' + groupe.nom + (groupe.date_naissance ?? '')

        return (
            <Card key={cle}>
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-navy-100 pb-3">
                    <div>
                        <h2 className="font-bold text-navy-900">{groupe.nom}</h2>
                        <p className="text-xs text-navy-500">
                            {groupe.ecole ?? 'École non renseignée'}
                            {groupe.date_naissance ? ` · Né(e) le ${groupe.date_naissance}` : ' · Dates de naissance différentes — voir ci-dessous'}
                        </p>
                    </div>
                    <Badge tone={badge.tone}>{badge.label}</Badge>
                </div>

                {groupe.potentiel && (
                    <div className="mb-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-800">
                        <AlertTriangle className="mt-0.5 h-3.5 w-3.5 flex-none" />
                        <span>
                            Date de naissance manquante ou différente entre ces fiches — vérifiez qu'il s'agit bien du même
                            enfant avant de fusionner ou de supprimer.
                        </span>
                    </div>
                )}

                <div className="flex flex-col divide-y divide-navy-100">
                    {groupe.membres.map((membre) => (
                        <div key={membre.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div className="flex min-w-0 items-start gap-2.5">
                                <input
                                    type="checkbox"
                                    checked={selection.has(membre.id)}
                                    onChange={() => toggleSelection(membre.id)}
                                    className="mt-1 h-4 w-4 flex-none rounded border-navy-300 text-red-600 focus:ring-red-500"
                                    title="Sélectionner pour suppression"
                                />
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-semibold text-navy-900">Matricule {membre.matricule ?? '—'}</p>
                                        <Badge tone={membre.statut === 'actif' ? 'green' : 'neutral'}>
                                            {membre.statut === 'actif' ? 'Actif' : membre.statut}
                                        </Badge>
                                        {membre.total_versements > 0 && (
                                            <Badge tone="gold">Versé : {formatMontant(membre.total_versements)}</Badge>
                                        )}
                                    </div>
                                    <p className="mt-0.5 text-xs text-navy-500">
                                        Classe : {membre.classe ?? 'Sans classe'}
                                        {groupe.potentiel && ` · Né(e) le ${membre.date_naissance ?? '—'}`}
                                        {' '}· Créée le {membre.created_at ?? '—'}
                                        {membre.tuteur && (
                                            <> · Tuteur : {membre.tuteur.nom_complet} {membre.tuteur.telephone ? `(${membre.tuteur.telephone})` : ''}</>
                                        )}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    title="Consulter"
                                    onClick={() => navigate(`/eleves/${membre.id}`)}
                                    className="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-navy-500 hover:bg-cream-100 hover:text-navy-700"
                                >
                                    Consulter
                                </button>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    disabled={enCours !== null}
                                    onClick={() => void fusionner(groupe, membre)}
                                >
                                    <GitMerge className="h-3.5 w-3.5" />
                                    {enCours === `${groupe.nom}:${membre.id}` ? 'Fusion…' : 'Garder celle-ci'}
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </Card>
        )
    }

    return (
        <div className="flex flex-col gap-5">
            <PageHeader
                titre="Gestion des doublons"
                sousTitre="Fiches élèves portant le même nom, la même école et la même date de naissance. Choisissez laquelle garder pour chaque groupe."
                icon={GitMerge}
                actions={
                    <div className="flex flex-wrap justify-end gap-2">
                        {selection.size > 0 && (
                            <Button
                                type="button"
                                variant="danger"
                                disabled={suppressionEnCours}
                                onClick={() => void supprimerSelection()}
                            >
                                <Trash2 className="h-4 w-4" />
                                Supprimer la sélection ({selection.size})
                            </Button>
                        )}
                        <Button type="button" variant="secondary" onClick={() => void traiterAutomatiquement()}>
                            <WandSparkles className="h-4 w-4" />
                            Nettoyage automatique
                        </Button>
                        <Button type="button" variant="secondary" onClick={() => navigate('/eleves')}>
                            <ArrowLeft className="h-4 w-4" />
                            Retour aux élèves
                        </Button>
                    </div>
                }
            />

            {isLoading ? (
                <Spinner />
            ) : isError || !data ? (
                <ErrorState />
            ) : certains.length === 0 && potentiels.length === 0 ? (
                <Card>
                    <EmptyState label="Aucun doublon apparent n'a été trouvé." />
                </Card>
            ) : (
                <div className="flex flex-col gap-6">
                    {certains.length > 0 && (
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center gap-2 text-sm text-navy-600">
                                <UserRound className="h-4 w-4" />
                                {certains.length} groupe(s) de doublons certains, {totalFiches} fiche(s) à vérifier · Total versé : {formatMontant(totalVerse)}
                            </div>
                            {certains.map(renderGroupe)}
                        </div>
                    )}

                    {potentiels.length > 0 && (
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center gap-2 text-sm text-navy-600">
                                <AlertTriangle className="h-4 w-4 text-amber-500" />
                                {potentiels.length} doublon(s) potentiel(s) — même nom et même école, date de naissance manquante ou différente à vérifier
                            </div>
                            {potentiels.map(renderGroupe)}
                        </div>
                    )}
                </div>
            )}
        </div>
    )
}
