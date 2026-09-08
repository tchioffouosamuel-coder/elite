<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClasseBatchDestroyTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_supprime_plusieurs_classes_dun_coup(): void
    {
        $a = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2 A']);
        $b = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2 B']);
        $autre = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2 C']);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/classes/batch-delete', ['ids' => [$a->id, $b->id]]);

        $reponse->assertOk()->assertJsonPath('data.deleted', 2);

        $this->assertDatabaseMissing('classes', ['id' => $a->id]);
        $this->assertDatabaseMissing('classes', ['id' => $b->id]);
        $this->assertDatabaseHas('classes', ['id' => $autre->id]);
    }

    /** Les élèves d'une classe supprimée en masse sont désaffectés, pas supprimés — même règle que la suppression unitaire. */
    public function test_les_eleves_dune_classe_supprimee_en_masse_sont_juste_desaffectes(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2 A']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id, 'nom_complet' => 'Test Eleve',
            'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/classes/batch-delete', ['ids' => [$classe->id]])
            ->assertOk();

        $this->assertDatabaseHas('eleves', ['id' => $eleve->id, 'classe_id' => null]);
    }

    public function test_ids_vides_est_refuse(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/classes/batch-delete', ['ids' => []])
            ->assertStatus(422);
    }
}
