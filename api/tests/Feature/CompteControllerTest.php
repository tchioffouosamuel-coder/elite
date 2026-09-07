<?php

namespace Tests\Feature;

use App\Models\Complexe;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    /**
     * Le compte racine (super admin sans `school_id`, cf.
     * CompteController::comptesAccessibles()) n'a aucune école principale à
     * comparer : la restriction « même complexe » ne doit donc jamais
     * l'empêcher de s'attribuer l'accès à une école, quelle qu'elle soit.
     */
    public function test_le_super_admin_racine_peut_sattribuer_nimporte_quelle_ecole(): void
    {
        $ecole = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);

        $racine = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => null, 'is_active' => true,
        ]);
        $racine->assignRole('super_admin');

        $reponse = $this->actingAs($racine)->putJson("/api/v1/comptes-utilisateurs/{$racine->id}/ecoles", [
            'school_ids' => [$ecole->id],
        ]);

        $reponse->assertOk();
        $this->assertTrue($racine->fresh()->schools->pluck('id')->contains($ecole->id));
    }

    /** Un compte rattaché à une école reste restreint aux écoles du même complexe. */
    public function test_un_compte_avec_ecole_principale_reste_restreint_a_son_complexe(): void
    {
        $complexe = Complexe::create(['name' => 'Complexe A', 'code' => 'CA']);
        $ecolePrincipale = School::create(['complexe_id' => $complexe->id, 'name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $ecoleAutreComplexe = School::create(['name' => 'Autre complexe', 'code' => 'AC', 'type' => 'primaire', 'is_active' => true]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password',
            'school_id' => $ecolePrincipale->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $reponse = $this->actingAs($admin)->putJson("/api/v1/comptes-utilisateurs/{$admin->id}/ecoles", [
            'school_ids' => [$ecoleAutreComplexe->id],
        ]);

        $reponse->assertStatus(422);
    }
}
