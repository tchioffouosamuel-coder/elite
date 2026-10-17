<?php

namespace App\Support\Pdf;

use App\Models\BusVersement;
use App\Models\Versement;
use App\Support\SignatureVersement;
use App\Support\SignatureVersementBus;
use Endroid\QrCode\Builder\Builder;

/**
 * Reçu remis à la préinscription, au format du ticket papier (cf.
 * GabaritRecuTicket) : un seul ticket pour ce que la famille a versé ce
 * jour-là, scolarité et/ou transport.
 */
class RecuPreinscriptionGenerator
{
    public function build(?Versement $scolarite, ?BusVersement $bus): string
    {
        $scolarite?->loadMissing(['encaisseur', 'dossier.eleve.classe', 'dossier.anneeScolaire', 'dossier.school', 'dossier.versements']);
        $bus?->loadMissing(['encaisseur', 'affectation.eleve.classe', 'affectation.eleve.school', 'affectation.anneeScolaire']);

        $principal = $scolarite ?? $bus;
        $eleve = $scolarite?->dossier?->eleve ?? $bus?->affectation?->eleve;
        $school = $scolarite?->dossier?->school ?? $eleve?->school;
        $annee = $scolarite?->dossier?->anneeScolaire ?? $bus?->affectation?->anneeScolaire;

        $montants = [
            ["Nom de l'élève / Name of the student", mb_strtoupper($eleve?->nom_complet ?? '—')],
            ['Classe / Class', mb_strtoupper($eleve?->classeLibellePour($annee) ?? '—')],
        ];
        if ($scolarite) {
            $montants[] = ['Scolarité versée / School fee paid', GabaritRecuTicket::francs((int) $scolarite->montant)];
            // Total dû sur le dossier de l'année, net de remise — demande
            // explicite d'Elites-tech (point 7) : seuls le payé et le reste
            // apparaissaient jusqu'ici, jamais le total de la scolarité.
            $montants[] = ['Montant total de la scolarité / Total tuition amount due', GabaritRecuTicket::francs((int) $scolarite->dossier->total_du)];
        }
        if ($bus) {
            $montants[] = ['Frais de transport / Transport fee', GabaritRecuTicket::francs((int) $bus->montant).' ('.mb_strtoupper($bus->mois->translatedFormat('F Y')).')'];
        }
        $montants[] = ['Total versé / Total paid', GabaritRecuTicket::francs((int) ($scolarite?->montant ?? 0) + (int) ($bus?->montant ?? 0))];
        if ($scolarite) {
            $montants[] = ['Reste à payer / Balance due', GabaritRecuTicket::francs(max(0, $scolarite->dossier->total_du - $scolarite->dossier->total_paye))];
        }

        return (new GabaritRecuTicket)->rendre($school, [
            'titre' => 'REÇU DE PRÉINSCRIPTION / PRE-REGISTRATION RECEIPT',
            'mentions' => [
                ['Matricule / Student ID :', $eleve?->matricule ?: '—'],
                ['Année scolaire / School year', $annee?->libelle ?? '—'],
                ['Date de paiement / Payment date', $principal->date_versement->format('d/m/Y')],
                ['Mode de paiement / Payment method', GabaritRecuTicket::libelleMode($principal->mode)],
            ],
            'montants' => $montants,
            'versements' => $scolarite
                ? $scolarite->dossier->versements->whereNull('annule_le')->sortByDesc('date_versement')
                    ->map(fn (Versement $v) => [$v->date_versement->format('d/m/Y'), (int) $v->montant])->values()->all()
                : [[$bus->date_versement->format('d/m/Y'), (int) $bus->montant]],
            'accessoires' => null,
            'numero' => $scolarite && $bus ? $scolarite->numero_recu.' / '.$bus->numero_recu : $principal->numero_recu,
            'encaisseur' => $principal->encaisseur?->name,
            'qr' => $this->qrCode($scolarite, $bus),
            'annule' => $principal->estAnnule(),
        ]);
    }

    /** QR du versement de scolarité s'il y en a un, sinon de celui du transport. */
    private function qrCode(?Versement $scolarite, ?BusVersement $bus): ?string
    {
        try {
            $lien = $scolarite
                ? SignatureVersement::lienVerification($scolarite->id)
                : SignatureVersementBus::lienVerification($bus->id);

            return (new Builder)->build(data: $lien, size: 200, margin: 4)->getDataUri();
        } catch (\Throwable) {
            return null;
        }
    }
}
