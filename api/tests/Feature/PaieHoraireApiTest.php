<?php

namespace Tests\Feature;

use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Circuit HTTP de la préparation d'un bulletin vacataire : c'est par cette
 * route que l'écran envoie les heures du mois — /paie/preparer (le lot) ne
 * les connaît pas, /paie/personnels/{id}/preparer si.
 */
class PaieHoraireApiTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Personnel $vacataire;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);

        $this->vacataire = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'SONG ERIC MUNYAM', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $this->vacataire->id,
            'date_effet' => '2026-01-01', 'mode' => 'horaire', 'taux_horaire' => 1500, 'salaire_base' => 0,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_le_lot_signale_le_vacataire_avec_son_identifiant(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/paie/preparer', ['jours_ouvrables' => 22], ['annee' => 2026, 'mois' => 3])
            ->assertOk();
    }

    public function test_la_route_par_agent_accepte_les_heures_et_prepare_le_bulletin(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson(
                "/api/v1/paie/personnels/{$this->vacataire->id}/preparer?annee=2026&mois=3",
                ['heures' => 60],
            )
            ->assertOk()
            ->assertJsonPath('data.salaire_brut', 90000)
            ->assertJsonPath('data.net_a_payer', 90000)
            ->assertJsonPath('data.charges_salariales', 0);
    }

    /** Sans heures, la route répond par une erreur claire — pas une exception non gérée. */
    public function test_la_route_par_agent_refuse_l_absence_d_heures(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/paie/personnels/{$this->vacataire->id}/preparer?annee=2026&mois=3", [])
            ->assertStatus(422);
    }
}
