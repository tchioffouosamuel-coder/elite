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
    private function agent(array $permissionsDeLaFonction, ?School $ecole = null): User
    {
        $schoolId = ($ecole ?? $this->school)->id;
        $fonction = FonctionReferentiel::create([
            'school_id' => $schoolId,
            'label_fr' => 'Fonction de test '.uniqid(),
        ]);
        $fonction->synchroniserPermissions($permissionsDeLaFonction);

        $user = User::create([
            'name' => 'Agent', 'email' => 'agent'.uniqid().'@test.local', 'password' => 'password',
            'school_id' => $schoolId, 'is_active' => true,
        ]);

        Personnel::create([
            'school_id' => $schoolId,
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
        $user = $this->agent(['eleves.view', 'appel.saisir']);

        $this->assertTrue($user->aLaPermission('eleves.view'));
        $this->assertTrue($user->aLaPermission('appel.saisir'));
        $this->assertFalse($user->aLaPermission('eleves.create'));

        // Le Gate doit rendre le même verdict que aLaPermission(), sinon
        // `can()` dans le code et le middleware divergeraient.
        $this->assertTrue($user->can('appel.saisir'));
        $this->assertFalse($user->can('eleves.create'));
    }

    public function test_les_privileges_du_role_et_de_la_fonction_se_cumulent(): void
    {
        $user = $this->agent(['eleves.view']);
        $role = Role::create(['name' => 'econome', 'guard_name' => 'web']);
        $role->syncPermissions(['tarifs.update']);
        $user->assignRole($role);

        $effectives = $user->fresh()->permissionsEffectives();

        $this->assertContains('eleves.view', $effectives);
        $this->assertContains('tarifs.update', $effectives);
    }

    public function test_retirer_un_privilege_de_la_fonction_le_retire_a_l_agent(): void
    {
        $user = $this->agent(['eleves.view', 'eleves.create']);
        $this->assertTrue($user->aLaPermission('eleves.create'));

        $user->fonction()->synchroniserPermissions(['eleves.view']);

        $this->assertFalse($user->fresh()->aLaPermission('eleves.create'));
    }

    public function test_le_super_admin_a_tous_les_privileges(): void
    {
        $user = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $this->assertTrue($user->aLaPermission('eleves.create'));
        $this->assertCount(count(CataloguePermissions::codes()), $user->permissionsEffectives());
    }

    public function test_une_action_sans_privilege_renvoie_un_403_nommant_le_privilege(): void
    {
        $user = $this->agent(['eleves.view']);

        $reponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/eleves', ['nom_complet' => 'Test', 'sexe' => 'M']);

        $reponse->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.permissions_requises', ['eleves.create']);

        $this->assertStringContainsString(
            CataloguePermissions::libelle('eleves.create'),
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
                'permissions' => ['eleves.view', 'eleves.create', 'privilege.inconnu'],
            ])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['eleves.view', 'eleves.create']);

        $this->assertTrue($agent->fresh()->aLaPermission('eleves.create'));
    }

    private function root(): User
    {
        $root = User::create([
            'name' => 'Root', 'email' => 'root'.uniqid().'@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $root->assignRole('super_admin');

        return $root;
    }

    public function test_le_super_admin_accorde_un_privilege_individuel_en_plus_de_la_fonction(): void
    {
        $agent = $this->agent(['eleves.view']);
        $personnelId = $agent->personnel->id;

        $this->actingAs($this->root(), 'sanctum')
            ->putJson("/api/v1/personnels/{$personnelId}/permissions", ['permissions' => ['finance.encaisser']])
            ->assertOk()
            ->assertJsonPath('data.permissions_directes', ['finance.encaisser']);

        $frais = $agent->fresh();
        $this->assertTrue($frais->aLaPermission('finance.encaisser'));
        // Le privilège de la fonction reste intact : le cumul n'écrase rien.
        $this->assertTrue($frais->aLaPermission('eleves.view'));
    }

    public function test_retirer_un_privilege_individuel_ne_touche_pas_celui_de_la_fonction(): void
    {
        $agent = $this->agent(['eleves.view']);
        $personnelId = $agent->personnel->id;
        $root = $this->root();

        $this->actingAs($root, 'sanctum')
            ->putJson("/api/v1/personnels/{$personnelId}/permissions", ['permissions' => ['finance.encaisser']])
            ->assertOk();
        $this->actingAs($root, 'sanctum')
            ->putJson("/api/v1/personnels/{$personnelId}/permissions", ['permissions' => []])
            ->assertOk()
            ->assertJsonPath('data.permissions_directes', []);

        $frais = $agent->fresh();
        $this->assertFalse($frais->aLaPermission('finance.encaisser'));
        $this->assertTrue($frais->aLaPermission('eleves.view'));
    }

    public function test_la_lecture_des_privileges_individuels_distingue_fonction_et_direct(): void
    {
        $agent = $this->agent(['eleves.view']);
        $personnelId = $agent->personnel->id;
        $agent->givePermissionTo('finance.encaisser');

        $this->actingAs($this->root(), 'sanctum')
            ->getJson("/api/v1/personnels/{$personnelId}/permissions")
            ->assertOk()
            ->assertJsonPath('data.permissions_fonction', ['eleves.view'])
            ->assertJsonPath('data.permissions_directes', ['finance.encaisser']);
    }

    /**
     * Mode agrégé (super admin sans X-School-Id) : la liste du personnel porte
     * sur tout le complexe, les privilèges individuels doivent suivre le même
     * périmètre. Sinon un agent bien listé renvoie 404 dès qu'il dépend d'une
     * autre école que celle du compte.
     */
    public function test_les_privileges_individuels_d_un_agent_d_une_autre_ecole_du_complexe_sont_accessibles(): void
    {
        $autreEcole = School::create([
            'name' => 'Elites Maternelle', 'code' => 'EM', 'type' => 'maternelle', 'is_active' => true,
        ]);
        $agent = $this->agent(['eleves.view'], $autreEcole);
        $personnelId = $agent->personnel->id;
        $root = $this->root();

        $this->actingAs($root, 'sanctum')
            ->getJson("/api/v1/personnels/{$personnelId}/permissions")
            ->assertOk()
            ->assertJsonPath('data.permissions_fonction', ['eleves.view']);

        $this->actingAs($root, 'sanctum')
            ->putJson("/api/v1/personnels/{$personnelId}/permissions", ['permissions' => ['finance.encaisser']])
            ->assertOk()
            ->assertJsonPath('data.permissions_directes', ['finance.encaisser']);

        $this->assertTrue($agent->fresh()->aLaPermission('finance.encaisser'));
    }

    public function test_un_agent_ordinaire_ne_peut_pas_gerer_les_privileges_individuels(): void
    {
        $agent = $this->agent(CataloguePermissions::codes());
        $autre = $this->agent(['eleves.view']);

        $this->actingAs($agent, 'sanctum')
            ->getJson("/api/v1/personnels/{$autre->personnel->id}/permissions")
            ->assertStatus(403);
    }
}
