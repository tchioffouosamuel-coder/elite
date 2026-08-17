<?php

namespace Tests\Feature;

use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true,
        ]);
    }

    /** Agent rattaché à une fonction, sans rôle ni permission directe. */
    private function agent(array $permissionsDeLaFonction): User
    {
        $fonction = FonctionReferentiel::create([
            'school_id' => $this->school->id,
            'label_fr' => 'Fonction de test',
        ]);
        $fonction->synchroniserPermissions($permissionsDeLaFonction);

        $user = User::create([
            'name' => 'Agent', 'email' => 'agent@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);

        Personnel::create([
            'school_id' => $this->school->id,
            'user_id' => $user->id,
            'fonction_id' => $fonction->id,
            'nom_complet' => 'Agent de test',
            'sexe' => 'M',
            'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    public function test_un_agent_herite_des_privileges_de_sa_fonction(): void
    {
        $user = $this->agent(['eleves.view', 'appel.manage']);

        $this->assertTrue($user->aLaPermission('eleves.view'));
        $this->assertTrue($user->aLaPermission('appel.manage'));
        $this->assertFalse($user->aLaPermission('eleves.manage'));

        // Le Gate doit rendre le même verdict que aLaPermission(), sinon
        // `can()` dans le code et le middleware divergeraient.
        $this->assertTrue($user->can('appel.manage'));
        $this->assertFalse($user->can('eleves.manage'));
    }

    public function test_les_privileges_du_role_et_de_la_fonction_se_cumulent(): void
    {
        $user = $this->agent(['eleves.view']);
        $role = Role::create(['name' => 'econome', 'guard_name' => 'web']);
        $role->syncPermissions(['finance.manage']);
        $user->assignRole($role);

        $effectives = $user->fresh()->permissionsEffectives();

        $this->assertContains('eleves.view', $effectives);
        $this->assertContains('finance.manage', $effectives);
    }

    public function test_retirer_un_privilege_de_la_fonction_le_retire_a_l_agent(): void
    {
        $user = $this->agent(['eleves.view', 'eleves.manage']);
        $this->assertTrue($user->aLaPermission('eleves.manage'));

        $user->fonction()->synchroniserPermissions(['eleves.view']);

        $this->assertFalse($user->fresh()->aLaPermission('eleves.manage'));
    }

    public function test_le_super_admin_a_tous_les_privileges(): void
    {
        $user = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $this->assertTrue($user->aLaPermission('eleves.manage'));
        $this->assertCount(count(CataloguePermissions::codes()), $user->permissionsEffectives());
    }

    public function test_une_action_sans_privilege_renvoie_un_403_nommant_le_privilege(): void
    {
        $user = $this->agent(['eleves.view']);

        $reponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/eleves', ['nom_complet' => 'Test', 'sexe' => 'M']);

        $reponse->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.permissions_requises', ['eleves.manage']);

        $this->assertStringContainsString(
            CataloguePermissions::libelle('eleves.manage'),
            $reponse->json('message'),
        );
    }

    public function test_une_action_couverte_par_la_fonction_passe_le_middleware(): void
    {
        $user = $this->agent(['eleves.view']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/eleves')->assertOk();
    }

    public function test_l_administration_des_privileges_est_reservee_au_super_admin(): void
    {
        $agent = $this->agent(CataloguePermissions::codes());

        $this->actingAs($agent, 'sanctum')->getJson('/api/v1/permissions')->assertStatus(403);

        $root = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $root->assignRole('super_admin');

        $this->actingAs($root, 'sanctum')->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonCount(CataloguePermissions::parModule()->count(), 'data.modules');
    }

    public function test_le_super_admin_modifie_le_groupe_de_privileges_d_une_fonction(): void
    {
        $agent = $this->agent(['eleves.view']);
        $fonction = $agent->fonction();

        $root = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $root->assignRole('super_admin');

        $this->actingAs($root, 'sanctum')
            ->putJson("/api/v1/fonctions-referentiel/{$fonction->id}/permissions", [
                // « privilege.inconnu » ne protège aucune route : il doit être écarté.
                'permissions' => ['eleves.view', 'eleves.manage', 'privilege.inconnu'],
            ])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['eleves.view', 'eleves.manage']);

        $this->assertTrue($agent->fresh()->aLaPermission('eleves.manage'));
    }
}
