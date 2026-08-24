<?php

namespace Tests\Feature;

use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Services\PersonnelService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * `batchDelete()` portait le même défaut que l'attestation employeur :
 * `app('tenant.school_id')` (singulier) au lieu de `Tenant::schoolIds()` —
 * en mode agrégé, une sélection couvrant plusieurs écoles du complexe
 * échouait dès le premier agent d'une école différente de celle où
 * retombe le singulier.
 */
class PersonnelBatchDeleteAgregeTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_suppression_en_masse_couvre_plusieurs_ecoles_en_mode_agrege(): void
    {
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $ecoleCompte = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $autreEcole = School::create(['name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true]);

        $superAdmin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $ecoleCompte->id, 'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        $fonctionA = FonctionReferentiel::create(['school_id' => $ecoleCompte->id, 'label_fr' => 'Enseignant']);
        $fonctionB = FonctionReferentiel::create(['school_id' => $autreEcole->id, 'label_fr' => 'Enseignant']);

        $service = app(PersonnelService::class);
        $agentA = $service->create($ecoleCompte->id, ['nom_complet' => 'AGENT A', 'fonction_id' => $fonctionA->id, 'statut' => 'actif']);
        $agentB = $service->create($autreEcole->id, ['nom_complet' => 'AGENT B', 'fonction_id' => $fonctionB->id, 'statut' => 'actif']);

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/v1/personnels/batch-delete', ['ids' => [$agentA->id, $agentB->id]])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertDatabaseMissing('personnels', ['id' => $agentA->id]);
        $this->assertDatabaseMissing('personnels', ['id' => $agentB->id]);
    }
}
