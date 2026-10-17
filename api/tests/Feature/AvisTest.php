<?php

namespace Tests\Feature;

use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Point 24 de la punch-list : espace d'évaluation — parent, enseignant ou
 * administration dépose un avis libre, consulté et filtré côté
 * administration (par rôle de l'auteur, notamment).
 */
class AvisTest extends TestCase
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
        foreach (['parent', 'enseignant', 'admin_ecole'] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions(RolePermissionSeeder::permissionsDuRole($roleName));
        }

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
    }

    private function utilisateur(string $role, string $email): User
    {
        $user = User::create([
            'name' => ucfirst($role), 'email' => $email, 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->assignRole($role);

        if ($role === 'enseignant') {
            $fonction = FonctionReferentiel::firstOrCreate(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
            Personnel::create([
                'school_id' => $this->school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
                'nom_complet' => 'Enseignant Test', 'sexe' => 'M', 'statut' => 'actif',
            ]);
        }

        return $user->fresh();
    }

    public function test_un_parent_peut_deposer_un_avis(): void
    {
        $parent = $this->utilisateur('parent', 'parent@test.local');

        $this->actingAs($parent, 'sanctum')
            ->postJson('/api/v1/avis', ['sujet' => 'Cantine', 'contenu' => 'Les repas sont très bons.', 'note' => 5])
            ->assertCreated();

        $this->assertDatabaseHas('avis', ['user_id' => $parent->id, 'sujet' => 'Cantine', 'note' => 5]);
    }

    public function test_un_enseignant_peut_deposer_un_avis_sans_note(): void
    {
        $prof = $this->utilisateur('enseignant', 'prof@test.local');

        $this->actingAs($prof, 'sanctum')
            ->postJson('/api/v1/avis', ['contenu' => 'La salle des profs manque de matériel.'])
            ->assertCreated();

        $this->assertDatabaseHas('avis', ['user_id' => $prof->id, 'note' => null]);
    }

    public function test_un_avis_sans_contenu_est_rejete(): void
    {
        $parent = $this->utilisateur('parent', 'parent2@test.local');

        $this->actingAs($parent, 'sanctum')
            ->postJson('/api/v1/avis', ['sujet' => 'Vide'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contenu']);
    }

    public function test_un_parent_sans_permission_avis_view_ne_peut_pas_consulter_la_liste_admin(): void
    {
        $parent = $this->utilisateur('parent', 'parent3@test.local');

        $this->actingAs($parent, 'sanctum')->getJson('/api/v1/avis')->assertForbidden();
    }

    public function test_ladministration_consulte_et_filtre_les_avis_par_role(): void
    {
        $admin = $this->utilisateur('admin_ecole', 'admin@test.local');
        $parent = $this->utilisateur('parent', 'parent4@test.local');
        $prof = $this->utilisateur('enseignant', 'prof4@test.local');

        $this->actingAs($parent, 'sanctum')->postJson('/api/v1/avis', ['contenu' => 'Avis du parent'])->assertCreated();
        $this->actingAs($prof, 'sanctum')->postJson('/api/v1/avis', ['contenu' => 'Avis de l\'enseignant'])->assertCreated();

        $reponse = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/avis?role=parent')
            ->assertOk();

        $reponse->assertJsonCount(1, 'data');
        $this->assertSame('Avis du parent', $reponse->json('data.0.contenu'));
    }

    public function test_un_utilisateur_consulte_ses_propres_avis(): void
    {
        $parent = $this->utilisateur('parent', 'parent5@test.local');

        $this->actingAs($parent, 'sanctum')->postJson('/api/v1/avis', ['contenu' => 'Mon premier avis'])->assertCreated();

        $this->actingAs($parent, 'sanctum')
            ->getJson('/api/v1/mes-avis')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
