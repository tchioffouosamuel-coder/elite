import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Ban, Eye, GitMerge, ReceiptText, UserRound, WandSparkles } from 'lucide-react'
import {
    annulerVersement,
    fetchVersementsDoublons,
    traitementAutomatiqueVersementsDoublons,
    type GroupeVersementDoublon,
    type VersementDoublonItem,
} from '@/features/finance/api'
import { useAuthStore } from '@/shared/store/authStore'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import type { ApiError } from '@/shared/types/api'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { EmptyState, ErrorState, Spinner } from '@/shared/ui/Feedback'
import { PageHeader } from '@/shared/ui/PageHeader'

const formatMontant = (montant: number) => `${new Intl.NumberFormat('fr-FR').format(montant)} FCFA`

const MODES_LIBELLES: Record<string, string> = {
    especes: 'Espèces',
    mobile_money: 'Mobile Money',
    virement: 'Virement',
    cheque: 'Chèque',
    depot_bancaire: 'Dépôt bancaire',
}

export function VersementsDoublonsPage() {
    const navigate = useNavigate()
    const can = useAuthStore((state) => state.can)
    const queryClient = useQueryClient()
    const [enTraitement, setEnTraitement] = useState<number | null>(null)
    const { data, isLoading, isError } = useQuery({
        queryKey: ['finance', 'versements-doublons'],
        queryFn: fetchVersementsDoublons,
    })

    const groupes = data?.groupes ?? []

    const rafraichir = () => {
        queryClient.invalidateQueries({ queryKey: ['finance', 'versements-doublons'] })
        queryClient.invalidateQueries({ queryKey: ['scolarite'] })
    }

    const traiterAutomatiquement = async () => {
        const confirme = await confirmer({
            titre: 'Annuler les doublons certains ?',
            message: 'Seules les paires de versements identiques (même élève, même montant) encaissés à moins de 30 minutes d\'intervalle seront annulées. Le versement le plus récent des deux est annulé, l\'autre reste au registre.',
            action: 'Traiter automatiquement',
        })
        if (!confirme) return

        try {
            const resultat = await traitementAutomatiqueVersementsDoublons()
            rafraichir()
            succes(`${resultat.annules} doublon(s) de paiement annulé(s). Montant annulé : ${formatMontant(resultat.montant_annule)}.`)
        } catch (err) {
            erreur((err as ApiError).message)
        }
    }

    const annuler = async (groupe: GroupeVersementDoublon, versement: VersementDoublonItem) => {
        const confirme = await confirmer({
            titre: `Annuler le reçu ${versement.numero_recu} ?`,
            message: `${formatMontant(groupe.montant)} seront retirés du solde de ${groupe.eleve.nom_complet}. Le reçu reste au registre, marqué annulé.`,
            action: 'Annuler le reçu',
        })
        if (!confirme) return

        setEnTraitement(versement.id)
        try {
            await annulerVersement(versement.id, 'Doublon de paiement : même montant déjà encaissé pour cet élève.')
            succes('Reçu annulé.')
            rafraichir()
        } catch (err) {
            erreur((err as ApiError).message)
        } finally {
            setEnTraitement(null)
        }
    }

    if (!can('finance.view')) {
        return <ErrorState />
    }

    return (
        <div className="flex flex-col gap-5">
            <PageHeader
                titre="Doublons de paiement"
                sousTitre="Versements portant le même montant, pour le même élève. Vérifiez chaque reçu avant de l'annuler."
                icon={GitMerge}
                actions={
                    <div className="flex flex-wrap justify-end gap-2">
                        {can('finance.annuler') && (
                            <Button type="button" variant="secondary" onClick={() => void traiterAutomatiquement()}>
                                <WandSparkles className="h-4 w-4" />
                                Nettoyage automatique
                            </Button>
                        )}
                        <Button type="button" variant="secondary" onClick={() => navigate('/caisse')}>
                            <ArrowLeft className="h-4 w-4" />
                            Retour à la caisse
                        </Button>
                    </div>
                }
            />

            {isLoading ? (
                <Spinner />
            ) : isError || !data ? (
                <ErrorState />
            ) : groupes.length === 0 ? (
                <Card>
                    <EmptyState label="Aucun doublon de paiement apparent n'a été trouvé." />
                </Card>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="flex items-center gap-2 text-sm text-navy-600">
                        <UserRound className="h-4 w-4" />
                        {groupes.length} groupe(s) de doublons, {groupes.reduce((total, g) => total + g.versements.length, 0)} reçu(s) à vérifier · Montant en doublon : {formatMontant(data.total_montant)}
                    </div>

                    {groupes.map((groupe) => (
                        <Card key={`${groupe.dossier_id}:${groupe.montant}`}>
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-navy-100 pb-3">
                                <div>
                                    <h2 className="font-bold text-navy-900">{groupe.eleve.nom_complet}</h2>
                                    <p className="text-xs text-navy-500">
                                        Matricule : {groupe.eleve.matricule ?? '—'} · Classe : {groupe.eleve.classe ?? 'Sans classe'}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center justify-end gap-2">
                                    <span className="text-xs font-semibold text-navy-500">Montant : {formatMontant(groupe.montant)}</span>
                                    <Badge tone="red">{groupe.versements.length} reçus</Badge>
                                </div>
                            </div>

                            <div className="flex flex-col divide-y divide-navy-100">
                                {groupe.versements.map((versement) => (
                                    <div key={versement.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                        <div className="min-w-0">
                                            <p className="font-semibold text-navy-900">Reçu {versement.numero_recu}</p>
                                            <p className="text-xs text-navy-500">
                                                Versé le {versement.date_versement} · {MODES_LIBELLES[versement.mode] ?? versement.mode}
                                                {versement.reference_externe ? ` · Réf. ${versement.reference_externe}` : ''}
                                                {versement.encaisse_par ? ` · Encaissé par ${versement.encaisse_par}` : ''}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <button type="button" title="Voir le reçu" onClick={() => ouvrirDocument(`/versements/${versement.id}/recu`)} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700">
                                                <Eye className="h-4 w-4" />
                                            </button>
                                            <button type="button" title="Fiche élève" onClick={() => navigate(`/eleves/${groupe.eleve.id}`)} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700">
                                                <ReceiptText className="h-4 w-4" />
                                            </button>
                                            {can('finance.annuler') && (
                                                <button
                                                    type="button"
                                                    title="Annuler ce reçu"
                                                    disabled={enTraitement === versement.id}
                                                    onClick={() => void annuler(groupe, versement)}
                                                    className="rounded-lg p-1.5 text-red-400 hover:bg-red-50 hover:text-red-700 disabled:opacity-50"
                                                >
                                                    <Ban className="h-4 w-4" />
                                                </button>
                                            )}
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
