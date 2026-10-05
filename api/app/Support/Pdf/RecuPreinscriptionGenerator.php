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
            ['Name of the student', mb_strtoupper($eleve?->nom_complet ?? '—')],
            ['Student class', mb_strtoupper($eleve?->classe?->nom ?? '—')],
        ];
        if ($scolarite) {
            $montants[] = ['School Fee paid', GabaritRecuTicket::francs((int) $scolarite->montant)];
        }
        if ($bus) {
            $montants[] = ['Transport Fee', GabaritRecuTicket::francs((int) $bus->montant).' ('.mb_strtoupper($bus->mois->translatedFormat('F Y')).')'];
        }
        $montants[] = ['Total paid', GabaritRecuTicket::francs((int) ($scolarite?->montant ?? 0) + (int) ($bus?->montant ?? 0))];
        if ($scolarite) {
            $montants[] = ['Left to pay', GabaritRecuTicket::francs(max(0, $scolarite->dossier->total_du - $scolarite->dossier->total_paye))];
        }

        return (new GabaritRecuTicket)->rendre($school, [
            'titre' => 'REÇU DE PRÉINSCRIPTION',
            'mentions' => [
                ['Student ID :', $eleve?->matricule ?: '—'],
                ['school year', $annee?->libelle ?? '—'],
                ['payment date', $principal->date_versement->format('d/m/Y')],
                ['payment method', GabaritRecuTicket::libelleMode($principal->mode)],
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
