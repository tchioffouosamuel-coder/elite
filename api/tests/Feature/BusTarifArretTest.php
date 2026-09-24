<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Services\BusPaiementService;
use App\Services\BusService;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** Le transport se facture à l'arrêt (et non plus au trajet), net de la remise accordée à la souscription. */
class BusTarifArretTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private BusTrajet $trajet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanComptableSeeder::class);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);

        AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);

        $this->trajet = BusTrajet::create([
            'nom' => 'Ligne Nord',
            'tarif_aller_simple' => 6000,
            'tarif_aller_retour' => 10000,
        ]);
    }

    private function eleve(): Eleve
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e A']);

        return Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $classe->id,
            'matricule' => 'M' . random_int(1000, 9999),
            'nom_complet' => 'ELEVE TEST',
            'sexe' => 'M',
            'statut' => 'actif',
        ]);
    }

    private function service(): BusService
    {
        return app(BusService::class);
    }

    public function test_un_nouvel_arret_reprend_la_grille_de_son_trajet(): void
    {
        $arret = $this->service()->ajouterArret($this->trajet, ['nom' => 'Carrefour']);

        $this->assertSame(10000, $arret->tarif_aller_retour);
        $this->assertSame(6000, $arret->tarif_aller_simple);
    }

    public function test_la_souscription_facture_le_tarif_de_l_arret(): void
    {
        $arret = $this->service()->ajouterArret($this->trajet, ['nom' => 'Andrea Hotel', 'tarif_aller_retour' => 8000]);

        $affectation = $this->service()->affecterEleve($this->school->id, [
            'eleve_id' => $this->eleve()->id,
            'trajet_id' => $this->trajet->id,
            'arret_id' => $arret->id,
            'option_trajet' => 'aller_retour',
        ]);

        $this->assertSame(8000, $affectation->tarif_mensuel);
        $this->assertSame($arret->id, $affectation->arret_id);
    }

    public function test_un_arret_sans_tarif_retombe_sur_la_grille_du_trajet(): void
    {
        $arret = BusArret::create(['trajet_id' => $this->trajet->id, 'nom' => 'Importé', 'ordre' => 1]);

        $this->assertSame(10000, $arret->tarifPour('aller_retour'));
    }

    public function test_la_remise_de_souscription_reduit_le_du_mensuel(): void
    {
        $arret = $this->service()->ajouterArret($this->trajet, ['nom' => 'Andrea Hotel', 'tarif_aller_retour' => 8000]);

        $affectation = $this->service()->affecterEleve($this->school->id, [
            'eleve_id' => $this->eleve()->id,
            'trajet_id' => $this->trajet->id,
            'arret_id' => $arret->id,
            'option_trajet' => 'aller_retour',
            'remise' => 1000,
        ])->fresh('anneeScolaire');

        $this->assertSame(1000, $affectation->remise);
        $this->assertSame(7000, $affectation->tarif_net);
        $this->assertSame(7000, $affectation->situation_mensuelle[0]['du']);

        // L'encaissement attend désormais le tarif net de la remise.
        $moisCourant = $affectation->mois_couverture->first()->format('Y-m-d');
        $versement = app(BusPaiementService::class)->encaisser($affectation, ['mois' => $moisCourant, 'montant' => 7000]);
        $this->assertSame(7000, $versement->montant);
    }

    public function test_une_remise_superieure_au_tarif_est_refusee(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service()->affecterEleve($this->school->id, [
            'eleve_id' => $this->eleve()->id,
            'trajet_id' => $this->trajet->id,
            'option_trajet' => 'aller_retour',
            'remise' => 20000,
        ]);
    }

    public function test_changer_d_arret_relit_le_tarif_du_nouvel_arret(): void
    {
        $proche = $this->service()->ajouterArret($this->trajet, ['nom' => 'Proche', 'tarif_aller_retour' => 5000]);
        $loin = $this->service()->ajouterArret($this->trajet, ['nom' => 'Loin', 'tarif_aller_retour' => 12000]);

        $affectation = $this->service()->affecterEleve($this->school->id, [
            'eleve_id' => $this->eleve()->id,
            'trajet_id' => $this->trajet->id,
            'arret_id' => $proche->id,
            'option_trajet' => 'aller_retour',
        ]);

        $modifiee = $this->service()->modifierAffectation($affectation, ['arret_id' => $loin->id, 'remise' => 2000]);

        $this->assertSame(12000, $modifiee->tarif_mensuel);
        $this->assertSame(10000, $modifiee->tarif_net);
    }
}
