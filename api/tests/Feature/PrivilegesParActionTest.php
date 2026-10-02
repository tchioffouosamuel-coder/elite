<?php

namespace Tests\Feature;

use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Support\AnciensPrivileges;
use App\Support\Attributions;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Les privilèges `X.manage` ont été découpés en un privilège par action
 * (créer, modifier, supprimer, importer, valider…). Ces tests tiennent les
 * trois promesses du découpage : le catalogue n'a plus de code global, chaque
 * route exige l'action qui la concerne, et la bascule ne déplace aucun droit.
 */
class PrivilegesParActionTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_01_100000_decouper_privileges_manage_par_action.php';

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

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

    public function test_le_catalogue_ne_porte_plus_de_privilege_global(): void
    {
        $parents = [];

        foreach (AnciensPrivileges::REMPLACEMENTS as $ancien => $remplacants) {
            $this->assertFalse(CataloguePermissions::existe($ancien), "« {$ancien} » devrait avoir quitté le catalogue.");

            foreach ($remplacants as $code) {
                $this->assertTrue(CataloguePermissions::existe($code), "« {$code} » remplace « {$ancien} » mais n'est pas au catalogue.");
                // Un seul parent par code : sinon la bascule ferait passer un
                // droit d'un module à l'autre.
                $this->assertArrayNotHasKey($code, $parents, "« {$code} » descend de deux anciens privilèges.");
                $parents[$code] = $ancien;
            }
        }

        foreach (CataloguePermissions::codes() as $code) {
            $this->assertStringEndsNotWith('.manage', $code);
        }
    }

    public function test_les_compositions_par_defaut_ne_citent_que_des_privileges_du_catalogue(): void
    {
        foreach (RolePermissionSeeder::ROLE_PERMISSIONS as $role => $motifs) {
            foreach ($motifs as $motif) {
                // Un motif `entité.*` mal orthographié ne lèverait aucune
                // erreur : il n'accorderait simplement rien.
                $codes = CataloguePermissions::developper([$motif]);

                $this->assertNotEmpty($codes, "Le motif « {$motif} » du rôle {$role} ne désigne aucun privilège.");

                foreach ($codes as $code) {
                    $this->assertTrue(CataloguePermissions::existe($code), "Rôle {$role} : « {$code} » n'est pas au catalogue.");
                }
            }
        }

        foreach (Attributions::codes() as $attribution) {
            foreach (Attributions::permissions($attribution) as $code) {
                $this->assertTrue(CataloguePermissions::existe($code), "Attribution {$attribution} : « {$code} » n'est pas au catalogue.");
            }
        }
    }

    public function test_chaque_action_exige_son_propre_privilege(): void
    {
        // Peut enregistrer et traiter une réclamation, pas la supprimer.
        $user = $this->agent(['revendications.view', 'revendications.create', 'revendications.update']);

        $creation = $this->actingAs($user, 'sanctum')->postJson('/api/v1/revendications', []);
        $this->assertNotSame(403, $creation->status(), 'La création devait franchir le contrôle des privilèges.');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/revendications/1')
            ->assertStatus(403)
            ->assertJsonPath('errors.permissions_requises', ['revendications.delete']);
    }

    public function test_supprimer_ne_donne_pas_le_droit_de_modifier(): void
    {
        $user = $this->agent(['personnel.view', 'departements.delete']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/departements', ['label_fr' => 'Sciences'])
            ->assertStatus(403)
            ->assertJsonPath('errors.permissions_requises', ['departements.create']);

        $suppression = $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/departements/999');
        $this->assertNotSame(403, $suppression->status());
    }

    public function test_la_bascule_reporte_un_ancien_privilege_sur_ses_remplacants(): void
    {
        $ancien = Permission::create(['name' => 'eleves.manage', 'guard_name' => 'web']);

        $role = Role::create(['name' => 'secretaire', 'guard_name' => 'web']);
        $role->givePermissionTo($ancien);

        $fonction = FonctionReferentiel::create(['school_id' => $this->school->id, 'label_fr' => 'Secrétaire']);
        $fonction->permissions()->attach($ancien->id);

        $compte = User::create([
            'name' => 'Direct', 'email' => 'direct@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $compte->givePermissionTo($ancien);

        (require database_path(self::MIGRATION))->up();

        $attendus = AnciensPrivileges::REMPLACEMENTS['eleves.manage'];

        $this->assertNull(Permission::where('name', 'eleves.manage')->first());
        $this->assertEqualsCanonicalizing($attendus, $role->fresh()->permissions->pluck('name')->all());
        $this->assertEqualsCanonicalizing($attendus, $fonction->fresh()->codesPermissions()->all());
        $this->assertEqualsCanonicalizing($attendus, $compte->fresh()->getDirectPermissions()->pluck('name')->all());
    }

    public function test_le_profil_sert_encore_l_ancien_code_aux_clients_anterieurs_au_decoupage(): void
    {
        $user = $this->agent(['eleves.view', 'eleves.update']);

        $permissions = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->json('data.permissions');

        $this->assertContains('eleves.update', $permissions);
        $this->assertContains('eleves.manage', $permissions);
        $this->assertNotContains('personnel.manage', $permissions);

        // L'alias n'est qu'un affichage : il n'ouvre aucune route.
        $this->assertFalse($user->aLaPermission('eleves.manage'));
        $this->assertFalse($user->aLaPermission('eleves.delete'));
    }
}
