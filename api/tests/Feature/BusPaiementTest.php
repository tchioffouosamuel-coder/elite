<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\BusTrajet;
use App\Models\Classe;
use App\Models\EcritureComptable;
use App\Models\Eleve;
use App\Models\School;
use App\Services\BusPaiementService;
use App\Services\BusService;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusPaiementTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private BusTrajet $trajet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanComptableSeeder::class);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);

        $this->trajet = BusTrajet::create([
            'school_id' => $this->school->id,
            'nom' => 'Ligne Nord',
            'tarif_aller_retour' => 15000,
        ]);
    }

    private function eleve(): Eleve
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'ACCOUNTING 1-A']);

        return Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $classe->id,
            'matricule' => '23PRIM2',
            'nom_complet' => 'FOMESSO LIMA MARK JOEL',
            'sexe' => 'M',
            'statut' => 'actif',
        ]);
    }

    private function affecter(?string $souscritLe = null): BusAffectation
    {
        $affectation = BusAffectation::create([
            'eleve_id' => $this->eleve()->id,
            'trajet_id' => $this->trajet->id,
            'annee_scolaire_id' => $this->annee->id,
            'tarif_mensuel' => 15000,
            'option_trajet' => 'aller_retour',
            'statut' => 'actif',
        ]);

        if ($souscritLe) {
            $affectation->forceFill(['created_at' => $souscritLe])->save();
        }

        return $affectation->fresh('anneeScolaire');
    }

    private function service(): BusPaiementService
    {
        return app(BusPaiementService::class);
    }

    /** Souscrite dès la rentrée : les mois dus vont de septembre à juillet, soit 11 mois. */
    public function test_les_mois_dus_couvrent_toute_l_annee_pour_une_souscription_de_rentree(): void
    {
        $affectation = $this->affecter('2026-09-05');

        $this->assertCount(11, $affectation->mois_couverture);
        $this->assertSame(165000, $affectation->total_du);
    }

    /** Souscrite en janvier : les mois avant la souscription ne sont jamais dus. */
    public function test_les_mois_dus_partent_de_la_souscription_pas_de_la_rentree(): void
    {
        $affectation = $this->affecter('2027-01-10');

        $this->assertCount(7, $affectation->mois_couverture); // janvier -> juillet
        $this->assertSame(105000, $affectation->total_du);
    }

    public function test_un_encaissement_regle_le_mois_choisi(): void
    {
        $affectation = $this->affecter('2026-09-05');

        $versement = $this->service()->encaisser($affectation, ['mois' => '2026-09-01', 'montant' => 15000]);

        $this->assertSame('RB-EBT-0001', $versement->numero_recu);

        $situation = $affectation->fresh(['anneeScolaire', 'versements'])->situation_mensuelle;
        $this->assertSame('solde', $situation[0]['statut']);
        $this->assertSame('impaye', $situation[1]['statut']);
    }

    public function test_un_mois_hors_couverture_est_refuse(): void
    {
        $affectation = $this->affecter('2026-09-05');

        $this->expectExceptionMessage("n'est pas couvert par cette souscription");

        $this->service()->encaisser($affectation, ['mois' => '2027-08-01', 'montant' => 15000]);
    }

    public function test_l_encaissement_credite_le_compte_transport(): void
    {
        $affectation = $this->affecter('2026-09-05');
        $versement = $this->service()->encaisser($affectation, ['mois' => '2026-09-01', 'montant' => 15000, 'mode' => 'mobile_money']);

        $ecritures = EcritureComptable::where('origine_id', $versement->id)
            ->where('origine_type', $versement->getMorphClass())
            ->with('compte')
            ->get();

        $debit = $ecritures->firstWhere('sens', 'debit');
        $this->assertSame('578', $debit->compte->code);

        $credit = $ecritures->firstWhere('sens', 'credit');
        $this->assertSame('703', $credit->compte->code);
    }

    public function test_l_annulation_neutralise_sans_supprimer(): void
    {
        $affectation = $this->affecter('2026-09-05');
        $versement = $this->service()->encaisser($affectation, ['mois' => '2026-09-01', 'montant' => 15000]);

        $this->service()->annuler($versement, 'Erreur de saisie');

        $this->assertDatabaseHas('bus_versements', ['id' => $versement->id, 'motif_annulation' => 'Erreur de saisie']);
        $this->assertSame(0, $affectation->fresh(['anneeScolaire', 'versements'])->total_paye);
    }

    /** Un reçu déjà remis ne doit jamais disparaître : retirer l'élève du bus suspend l'affectation plutôt que de la supprimer. */
    public function test_retirer_une_affectation_deja_payee_la_suspend_au_lieu_de_la_supprimer(): void
    {
        $affectation = $this->affecter('2026-09-05');
        $this->service()->encaisser($affectation, ['mois' => '2026-09-01', 'montant' => 15000]);

        app(BusService::class)->retirerAffectation($affectation);

        $this->assertDatabaseHas('bus_affectations', ['id' => $affectation->id, 'statut' => 'suspendu']);
        $this->assertDatabaseCount('bus_versements', 1);
    }

    public function test_retirer_une_affectation_jamais_payee_la_supprime(): void
    {
        $affectation = $this->affecter('2026-09-05');

        app(BusService::class)->retirerAffectation($affectation);

        $this->assertDatabaseMissing('bus_affectations', ['id' => $affectation->id]);
    }
}
