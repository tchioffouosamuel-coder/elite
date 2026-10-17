<?php

namespace App\Support\Pdf;

use App\Models\BusVersement;
use App\Support\SignatureVersementBus;
use Endroid\QrCode\Builder\Builder;

/**
 * Reçu du transport scolaire, au format du ticket papier (cf.
 * GabaritRecuTicket) — un reçu par mensualité, avec en marge l'historique
 * des mois déjà réglés pour cette souscription.
 */
class RecuVersementBusGenerator
{
    public function build(BusVersement $versement): string
    {
        $versement->loadMissing(['affectation.eleve.classe', 'affectation.eleve.school', 'affectation.anneeScolaire', 'affectation.trajet', 'affectation.versements', 'encaisseur', 'annulePar']);

        $affectation = $versement->affectation;
        $eleve = $affectation->eleve;

        $montants = [
            ["Nom de l'élève / Name of the student", mb_strtoupper($eleve->nom_complet)],
            ['Classe / Class', mb_strtoupper($eleve->classeLibellePour($affectation->anneeScolaire) ?? '—')],
            ['Trajet / Route', mb_strtoupper($affectation->trajet?->nom ?? '—')],
            ['Mois couvert / Month covered', mb_strtoupper($versement->mois->translatedFormat('F Y'))],
        ];
        if ($versement->remise > 0) {
            $montants[] = ['Remise / Discount', GabaritRecuTicket::francs((int) $versement->remise)];
        }
        // Tarif mensuel net de remise de souscription — même calcul que
        // `BusAffectation::tarif_net`, utilisé par BusPaiementService pour
        // déterminer ce qui est dû sur le mois (point 7 : total dû manquant).
        $montants[] = ['Montant total du mois / Total month amount due', GabaritRecuTicket::francs((int) $affectation->tarif_net)];
        $montants[] = ['Montant versé / Amount paid', GabaritRecuTicket::francs((int) $versement->montant)];

        // L'école de l'élève, pas celle du trajet : un trajet dessert souvent
        // plusieurs écoles du complexe et son `school_id` peut même être vide
        // (cf. BusSouscriptionImport::resoudreTrajet).
        return (new GabaritRecuTicket)->rendre($eleve->school, [
            'titre' => 'REÇU DE PAIEMENT DES FRAIS DE TRANSPORT / TRANSPORT FEE PAYMENT RECEIPT',
            'mentions' => [
                ['Matricule / Student ID :', $eleve->matricule ?: '—'],
                ['Année scolaire / School year', $affectation->anneeScolaire?->libelle ?? '—'],
                ['Date de paiement / Payment date', $versement->date_versement->format('d/m/Y')],
                ['Mode de paiement / Payment method', GabaritRecuTicket::libelleMode($versement->mode)],
            ],
            'montants' => $montants,
            'versements' => $affectation->versements->whereNull('annule_le')
                ->sortByDesc(fn (BusVersement $v) => $v->date_versement->format('Y-m-d').'#'.str_pad((string) $v->id, 12, '0', STR_PAD_LEFT))
                ->map(fn (BusVersement $v) => [$v->date_versement->format('d/m/Y'), (int) $v->montant])->values()->all(),
            // Pas d'accessoires sur une souscription de transport.
            'accessoires' => null,
            'numero' => $versement->numero_recu,
            'encaisseur' => $versement->encaisseur?->name,
            // Traçabilité (point 12) : voir le même choix sur RecuVersementGenerator.
            'enregistre_le' => $versement->created_at,
            'qr' => $this->qrCode($versement),
            'annule' => $versement->estAnnule(),
            'annule_par' => $versement->annulePar?->name,
            'annule_le' => $versement->annule_le,
        ]);
    }

    /** Code QR vers la page publique de vérification d'authenticité (cf. SignatureVersementBus). */
    private function qrCode(BusVersement $versement): ?string
    {
        try {
            return (new Builder)->build(data: SignatureVersementBus::lienVerification($versement->id), size: 200, margin: 4)->getDataUri();
        } catch (\Throwable) {
            return null;
        }
    }
}
