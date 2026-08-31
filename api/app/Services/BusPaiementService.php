<?php

namespace App\Services;

use App\Models\BusAffectation;
use App\Models\BusVersement;
use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Encaissement du transport scolaire.
 *
 * Contrairement à la scolarité, le bus se paie au mois : chaque versement
 * couvre un mois précis d'une souscription (`BusAffectation`), dans un
 * registre séparé (`bus_versements`), avec son propre reçu numéroté — jamais
 * mélangé à l'encaissement de scolarité, qui suit son propre calendrier
 * (tranches) et sa propre logique de ventilation.
 */
class BusPaiementService extends BaseService
{
    private const COMPTE_TRESORERIE = [
        'especes' => '571',
        'mobile_money' => '578',
        'virement' => '521',
        'cheque' => '521',
        'depot_bancaire' => '521',
    ];

    /** Même compte de produits que les frais annexes : le plan comptable de l'école range le transport parmi les « autres services scolaires ». */
    private const COMPTE_TRANSPORT = '703';

    public function __construct(private readonly NumeroRecuService $numeros) {}

    /**
     * Enregistre le règlement d'un mois de transport et rend le versement,
     * reçu numéroté à l'appui.
     *
     * @param  array{mois: string, montant: int, date_versement?: string, mode?: string, reference_externe?: ?string, note?: ?string}  $donnees
     */
    public function encaisser(BusAffectation $affectation, array $donnees, ?int $encaissePar = null): BusVersement
    {
        $montant = (int) $donnees['montant'];

        if ($montant <= 0) {
            throw new RuntimeException('Le montant encaissé doit être supérieur à zéro.');
        }

        $mois = Carbon::parse($donnees['mois'])->startOfMonth();

        $couverture = $affectation->mois_couverture;
        if (! $couverture->contains(fn (Carbon $m) => $m->isSameMonth($mois))) {
            throw new RuntimeException("Ce mois n'est pas couvert par cette souscription.");
        }

        $affectation->loadMissing('trajet.school');
        $school = $affectation->trajet->school;

        return $this->transaction(function () use ($affectation, $donnees, $montant, $mois, $encaissePar, $school) {
            $versement = BusVersement::create([
                'school_id' => $school->id,
                'bus_affectation_id' => $affectation->id,
                'mois' => $mois,
                'numero_recu' => $this->numeros->attribuerBus($school, $affectation->annee_scolaire_id, $encaissePar),
                'date_versement' => $donnees['date_versement'] ?? Carbon::today()->toDateString(),
                'montant' => $montant,
                'mode' => $donnees['mode'] ?? 'especes',
                'reference_externe' => $donnees['reference_externe'] ?? null,
                'encaisse_par' => $encaissePar,
                'note' => $donnees['note'] ?? null,
            ]);

            $this->comptabiliser($versement, $mois);

            return $versement;
        });
    }

    public function annuler(BusVersement $versement, string $motif, ?int $annulePar = null): BusVersement
    {
        if ($versement->estAnnule()) {
            throw new RuntimeException('Ce reçu est déjà annulé.');
        }

        return $this->transaction(function () use ($versement, $motif, $annulePar) {
            $versement->update([
                'annule_le' => now(),
                'annule_par' => $annulePar,
                'motif_annulation' => $motif,
            ]);

            foreach ($versement->ecritures()->get() as $ecriture) {
                EcritureComptable::create([
                    'school_id' => $ecriture->school_id,
                    'annee_scolaire_id' => $ecriture->annee_scolaire_id,
                    'date_ecriture' => now()->toDateString(),
                    'libelle' => 'Annulation — '.$ecriture->libelle,
                    'montant' => $ecriture->montant,
                    'sens' => $ecriture->sens === 'debit' ? 'credit' : 'debit',
                    'compte_comptable_id' => $ecriture->compte_comptable_id,
                    'origine_type' => $versement->getMorphClass(),
                    'origine_id' => $versement->id,
                ]);
            }

            return $versement->fresh();
        });
    }

    /** Journal : la caisse est débitée du montant reçu, le compte de transport crédité. */
    private function comptabiliser(BusVersement $versement, Carbon $mois): void
    {
        $commun = [
            'school_id' => $versement->school_id,
            'annee_scolaire_id' => $versement->affectation->annee_scolaire_id,
            'date_ecriture' => $versement->date_versement,
            'origine_type' => $versement->getMorphClass(),
            'origine_id' => $versement->id,
        ];

        EcritureComptable::create($commun + [
            'libelle' => 'Encaissement transport scolaire — reçu '.$versement->numero_recu,
            'montant' => $versement->montant,
            'sens' => 'debit',
            'compte_comptable_id' => $this->compte(self::COMPTE_TRESORERIE[$versement->mode] ?? '571'),
        ]);

        EcritureComptable::create($commun + [
            'libelle' => 'Transport scolaire ('.$mois->translatedFormat('F Y').') — reçu '.$versement->numero_recu,
            'montant' => $versement->montant,
            'sens' => 'credit',
            'compte_comptable_id' => $this->compte(self::COMPTE_TRANSPORT),
        ]);
    }

    private function compte(string $code): ?int
    {
        return CompteComptable::where('code', $code)->value('id');
    }
}
