<?php

namespace Tests\Feature;

use App\Models\BudgetPersonnel;
use App\Models\Depense;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Services\BudgetPersonnelService;
use App\Services\DepenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Budget alloué à un membre du personnel : le solde se déduit des dépenses
 * imputées dessus (jamais stocké), une dépense ne peut pas le faire passer
 * sous zéro, et seul l'intéressé gère sa propre note de gestion.
 */
class BudgetPersonnelTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Personnel $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);
        $this->agent = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'MBARGA PAUL', 'sexe' => 'M', 'statut' => 'actif',
        ]);
    }

    private function budgets(): BudgetPersonnelService
    {
        return app(BudgetPersonnelService::class);
    }

    private function depenses(): DepenseService
    {
        return app(DepenseService::class);
    }

    /** @param array<string, mixed> $donnees */
    private function allouer(array $donnees = []): BudgetPersonnel
    {
        return $this->budgets()->allouer($this->school->id, [
            'personnel_id' => $this->agent->id,
            'libelle' => 'Fournitures de bureau',
            'montant_alloue' => 100000,
            'date_allocation' => '2026-09-01',
            ...$donnees,
        ], null);
    }

    private function depenserSurBudget(BudgetPersonnel $budget, int $montant): Depense
    {
        return $this->depenses()->enregistrer($this->school->id, [
            'libelle' => 'Achat papeterie',
            'montant' => $montant,
            'date_depense' => '2026-09-10',
            'source' => 'budget_personnel',
            'budget_personnel_id' => $budget->id,
        ]);
    }

    public function test_un_budget_alloue_a_un_solde_egal_au_montant(): void
    {
        $budget = $this->allouer();

        $this->assertSame(100000, $budget->montant_alloue);
        $this->assertSame(0, $budget->montant_depense);
        $this->assertSame(100000, $budget->solde);
        $this->assertSame('actif', $budget->statut);
    }

    public function test_une_depense_imputee_diminue_le_solde(): void
    {
        $budget = $this->allouer();
        $depense = $this->depenserSurBudget($budget, 30000);

        $this->assertSame($budget->id, $depense->budget_personnel_id);
        $this->assertSame('budget_personnel', $depense->source);
        $this->assertSame(70000, $budget->fresh()->solde);
    }

    public function test_une_depense_qui_depasse_le_solde_est_refusee(): void
    {
        $budget = $this->allouer(['montant_alloue' => 20000]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dépasse le solde disponible');

        $this->depenserSurBudget($budget, 25000);
    }

    public function test_le_budget_epuise_par_sa_derniere_depense_change_de_statut(): void
    {
        $budget = $this->allouer(['montant_alloue' => 50000]);
        $this->depenserSurBudget($budget, 50000);

        $this->assertSame('epuise', $budget->fresh()->statut);
        $this->assertSame(0, $budget->fresh()->solde);
    }

    public function test_une_depense_annulee_ne_pese_plus_sur_le_budget(): void
    {
        $budget = $this->allouer();
        $depense = $this->depenserSurBudget($budget, 30000);

        $this->depenses()->annuler($depense, 'Erreur de saisie');

        $this->assertSame(100000, $budget->fresh()->solde);
    }

    public function test_un_budget_clos_refuse_toute_nouvelle_depense(): void
    {
        $budget = $this->allouer();
        $this->budgets()->annuler($budget, 'Fin de mission');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('clôturé');

        $this->depenserSurBudget($budget->fresh(), 1000);
    }

    public function test_le_bilan_totalise_les_depenses_de_la_periode(): void
    {
        $budget = $this->allouer(['montant_alloue' => 100000]);
        $this->depenserSurBudget($budget, 20000);
        $this->depenserSurBudget($budget->fresh(), 15000);

        $bilan = $this->budgets()->bilan($budget->fresh());

        $this->assertSame(100000, $bilan['alloue']);
        $this->assertSame(35000, $bilan['depense']);
        $this->assertSame(65000, $bilan['solde']);
        $this->assertCount(2, $bilan['depenses']);
    }

    public function test_le_personnel_modifie_sa_propre_note_de_gestion(): void
    {
        $budget = $this->allouer();

        $reponse = $this->actingAs($this->compteDe($this->agent), 'sanctum')
            ->putJson("/api/v1/mon-espace/budgets/{$budget->id}/note-gestion", [
                'note_gestion' => 'Je réserve ce budget aux fournitures de la rentrée.',
            ])
            ->assertOk();

        $reponse->assertJsonPath('data.note_gestion', 'Je réserve ce budget aux fournitures de la rentrée.');
        $this->assertSame('Je réserve ce budget aux fournitures de la rentrée.', $budget->fresh()->note_gestion);
    }

    public function test_un_personnel_ne_peut_pas_modifier_la_note_d_un_budget_qui_n_est_pas_le_sien(): void
    {
        $budget = $this->allouer();

        $autre = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'AUTRE AGENT', 'sexe' => 'F', 'statut' => 'actif',
        ]);

        $this->actingAs($this->compteDe($autre), 'sanctum')
            ->putJson("/api/v1/mon-espace/budgets/{$budget->id}/note-gestion", ['note_gestion' => 'Tentative'])
            ->assertNotFound();

        $this->assertNull($budget->fresh()->note_gestion);
    }

    public function test_le_personnel_consulte_ses_budgets_dans_son_espace(): void
    {
        $this->allouer(['libelle' => 'Mission terrain', 'montant_alloue' => 60000]);

        $reponse = $this->actingAs($this->compteDe($this->agent), 'sanctum')
            ->getJson('/api/v1/mon-espace/budgets')
            ->assertOk();

        $reponse->assertJsonPath('data.budgets.0.libelle', 'Mission terrain');
        $reponse->assertJsonPath('data.budgets.0.solde', 60000);
    }

    private function compteDe(Personnel $personnel): User
    {
        $user = User::create([
            'school_id' => $personnel->school_id, 'name' => $personnel->nom_complet,
            'email' => strtolower(str_replace(' ', '.', $personnel->nom_complet)) . '@elites.test',
            'password' => Hash::make('secret'), 'is_active' => true,
        ]);

        $personnel->update(['user_id' => $user->id]);

        return $user;
    }
}
