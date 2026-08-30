<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Depense;
use App\Models\School;
use App\Services\AssuranceScolaireService;
use App\Services\BudgetFonctionnementService;
use App\Services\GouvernanceEcoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Budget de fonctionnement, assurance scolaire, conseil d'école et APEE —
 * les tableaux 21, 26, 29 et 30 du rapport de rentrée MINEDUB. Le dépensé du
 * budget de fonctionnement se recalcule toujours depuis les dépenses taguées
 * sur la rubrique, jamais stocké, pour ne pas pouvoir diverger du réel.
 */
class RentreeScolaireGouvernanceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Primaire', 'code' => 'EPP', 'type' => 'primaire', 'is_active' => true]);
        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30', 'is_active' => true,
        ]);
    }

    public function test_le_budget_de_fonctionnement_deduit_le_depense_des_depenses_taguees(): void
    {
        $service = app(BudgetFonctionnementService::class);

        $service->definirMontantPercu($this->school->id, $this->annee->id, 'fenassco', 200000);

        Depense::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->annee->id,
            'rubrique_budget_fonctionnement' => 'fenassco',
            'date_depense' => '2026-09-10',
            'libelle' => 'Maillots FENASSCO',
            'montant' => 50000,
            'statut' => 'payee',
        ]);

        // Une dépense annulée ne doit pas peser sur le tableau.
        Depense::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->annee->id,
            'rubrique_budget_fonctionnement' => 'fenassco',
            'date_depense' => '2026-09-12',
            'libelle' => 'Ballons',
            'montant' => 30000,
            'statut' => 'annulee',
        ]);

        $rapport = $service->rapport($this->school->id, $this->annee->id);
        $ligneFenassco = collect($rapport)->firstWhere('rubrique', 'fenassco');

        $this->assertSame(200000, $ligneFenassco['montant_percu']);
        $this->assertSame(50000, $ligneFenassco['montant_depense']);
        $this->assertSame(150000, $ligneFenassco['reste']);

        $ligneVide = collect($rapport)->firstWhere('rubrique', 'evaluation');
        $this->assertSame(0, $ligneVide['montant_percu']);
        $this->assertSame(0, $ligneVide['montant_depense']);
    }

    public function test_gere_les_assurances_scolaires_par_niveau(): void
    {
        $service = app(AssuranceScolaireService::class);

        $assurance = $service->create($this->school->id, [
            'annee_scolaire_id' => $this->annee->id,
            'libelle' => 'Niveau 1',
            'effectif' => 281,
            'nom_assureur' => 'AGC',
        ]);

        $this->assertSame(281, $assurance->effectif);

        $liste = $service->list($this->school->id, $this->annee->id);
        $this->assertCount(1, $liste);

        $service->update($assurance, ['effectif' => 290]);
        $this->assertSame(290, $assurance->fresh()->effectif);

        $service->delete($assurance);
        $this->assertCount(0, $service->list($this->school->id, $this->annee->id));
    }

    public function test_conseil_ecole_et_apee_sont_uniques_par_ecole_et_annee(): void
    {
        $service = app(GouvernanceEcoleService::class);

        $conseil = $service->definirConseilEcole($this->school->id, $this->annee->id, [
            'existe' => true,
            'president_nom' => 'Elvice FOMESSO',
            'duree_mandat' => '02ans',
        ]);
        $this->assertTrue($conseil->existe);

        // Un second appel sur la même école/année met à jour, ne duplique pas.
        $service->definirConseilEcole($this->school->id, $this->annee->id, ['existe' => true, 'president_nom' => 'Autre Nom']);
        $this->assertSame(1, \App\Models\ConseilEcole::where('school_id', $this->school->id)->count());
        $this->assertSame('Autre Nom', $service->conseilEcole($this->school->id, $this->annee->id)->president_nom);

        $apee = $service->definirApee($this->school->id, $this->annee->id, [
            'legalisee' => false,
            'president_nom' => 'DOUE GAELLE',
            'montant_percu' => 500000,
            'montant_depense' => 120000,
        ]);

        $this->assertSame(380000, $apee->montant_restant);
    }
}
