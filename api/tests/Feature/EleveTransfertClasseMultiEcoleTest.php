<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EleveTransfertClasseMultiEcoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_super_admin_peut_changer_un_eleve_de_classe_dans_son_ecole_en_mode_agrege(): void
    {
        Permission::firstOrCreate(['name' => 'eleves.update', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $ecoleA = School::create([
            'name' => 'École A',
            'code' => 'EA',
            'type' => 'primaire',
            'is_active' => true,
        ]);
        $ecoleB = School::create([
            'name' => 'École B',
            'code' => 'EB',
            'type' => 'primaire',
            'is_active' => true,
        ]);
        $classeA = Classe::create(['school_id' => $ecoleA->id, 'nom' => 'CP A']);
        $classeB = Classe::create(['school_id' => $ecoleB->id, 'nom' => 'CP B']);
        $eleve = Eleve::create([
            'school_id' => $ecoleB->id,
            'classe_id' => $classeB->id,
            'nom_complet' => 'Élève Exemple',
            'sexe' => 'F',
            'statut' => 'actif',
        ]);

        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'super-admin@test.local',
            'password' => 'password',
            'school_id' => $ecoleA->id,
            'is_active' => true,
        ]);
        $admin->assignRole('super_admin');
        $admin->givePermissionTo('eleves.update');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/eleves/{$eleve->id}", ['classe_id' => $classeB->id])
            ->assertOk();

        $this->assertSame($classeB->id, $eleve->fresh()->classe_id);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/eleves/{$eleve->id}", ['classe_id' => $classeA->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('classe_id');

        $this->assertSame($classeB->id, $eleve->fresh()->classe_id);
    }
}
