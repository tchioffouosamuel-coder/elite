<?php

namespace App\Support\Pdf;

use App\Models\DossierScolarite;
use App\Models\Versement;
use App\Support\SignatureVersement;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Support\Collection;

/**
 * Reçu d'encaissement de la scolarité, au format du ticket papier de
 * l'établissement (cf. GabaritRecuTicket) : mentions de l'élève, montant dû,
 * versé et restant, historiques des versements et des accessoires.
 */
class RecuVersementGenerator
{
    public function build(Versement $versement): string
    {
        $versement->loadMissing(['lignes', 'encaisseur', 'dossier.eleve.classe', 'dossier.anneeScolaire', 'dossier.fraisAnnexes', 'dossier.versements.lignes']);

        $dossier = $versement->dossier;
        $eleve = $dossier->eleve;
        $busSeul = $this->estBusSeul($versement);

        [$du, $paye, $reste] = $busSeul ? $this->montantsBus($versement, $dossier) : $this->montantsScolarite($versement, $dossier);

        $montants = [
            ['Name of the student', mb_strtoupper($eleve->nom_complet)],
            ['Student class', mb_strtoupper($eleve->classe?->nom ?? '—')],
            // Montant net : `total_du` tient déjà compte d'une éventuelle remise.
            [$busSeul ? 'Transport Fee' : 'School Fee', GabaritRecuTicket::francs($du)],
            ['Amount paid', GabaritRecuTicket::francs($paye)],
            ['Left to pay', GabaritRecuTicket::francs($reste)],
        ];

        return (new GabaritRecuTicket)->rendre($dossier->school, [
            'titre' => $busSeul ? 'REÇU DE PAIEMENT DES FRAIS DE TRANSPORT' : 'REÇU DE PAIEMENT DES FRAIS DE SCOLARITÉ',
            'mentions' => [
                ['Student ID :', $eleve->matricule ?: '—'],
                ['school year', $dossier->anneeScolaire?->libelle ?? '—'],
                ['payment date', $versement->date_versement->format('d/m/Y')],
                ['payment method', GabaritRecuTicket::libelleMode($versement->mode)],
            ],
            'montants' => $montants,
            'versements' => $this->valides($dossier)->sortByDesc(fn (Versement $v) => $this->ordre($v))
                ->map(fn (Versement $v) => [$v->date_versement->format('d/m/Y'), (int) $v->montant])->values()->all(),
            'accessoires' => collect($dossier->rubriques)->where('cle', 'frais_annexe')->where('montant_paye', '>', 0)
                ->map(fn (array $r) => [$r['libelle'], (int) $r['montant_paye']])->values()->all(),
            'numero' => $versement->numero_recu,
            'encaisseur' => $versement->encaisseur?->name,
            'qr' => $this->qrCode($versement),
            'annule' => $versement->estAnnule(),
        ]);
    }

    /**
     * Total dû, versé ce jour, et reste après CE versement — en cumulant tous
     * les versements valides jusqu'à lui (pas seulement celui du jour), pour
     * qu'un reçu réimprimé plus tard affiche toujours le même reste.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function montantsScolarite(Versement $versement, DossierScolarite $dossier): array
    {
        $cumul = (int) $this->jusquA($dossier, $versement)->sum('montant');

        return [(int) $dossier->total_du, (int) $versement->montant, max(0, (int) $dossier->total_du - $cumul)];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function montantsBus(Versement $versement, DossierScolarite $dossier): array
    {
        $du = (int) (collect($dossier->rubriques)->firstWhere('cle', 'bus')['montant_du'] ?? 0);
        $paye = (int) $versement->lignes->where('affectation', 'bus')->sum('montant');
        $cumul = (int) $this->jusquA($dossier, $versement)
            ->sum(fn (Versement $v) => $v->lignes->where('affectation', 'bus')->sum('montant'));

        return [$du, $paye, max(0, $du - $cumul)];
    }

    /** @return Collection<int, Versement> */
    private function valides(DossierScolarite $dossier)
    {
        return $dossier->versements->whereNull('annule_le');
    }

    /** Clé de tri chronologique : date du versement, puis ordre de saisie. */
    private function ordre(Versement $v): string
    {
        return $v->date_versement->format('Y-m-d').'#'.str_pad((string) $v->id, 12, '0', STR_PAD_LEFT);
    }

    /** Versements valides du dossier antérieurs ou égaux à `$courant`, lui compris. */
    private function jusquA(DossierScolarite $dossier, Versement $courant)
    {
        return $this->valides($dossier)
            ->filter(fn (Versement $v) => $this->ordre($v) <= $this->ordre($courant))
            ->when($courant->estAnnule(), fn ($c) => $c->push($courant));
    }

    private function estBusSeul(Versement $versement): bool
    {
        $lignes = $versement->lignes->where('montant', '>', 0);

        return $lignes->isNotEmpty() && $lignes->every(fn ($ligne) => $ligne->affectation === 'bus');
    }

    /** Code QR vers la page publique de vérification d'authenticité (cf. SignatureVersement). */
    private function qrCode(Versement $versement): ?string
    {
        try {
            return (new Builder)->build(data: SignatureVersement::lienVerification($versement->id), size: 200, margin: 4)->getDataUri();
        } catch (\Throwable) {
            return null;
        }
    }
}
