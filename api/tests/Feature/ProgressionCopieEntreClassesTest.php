<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\ProgressionColonne;
use App\Models\ProgressionItem;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Copie des leçons d'une matière d'une classe vers une ou plusieurs autres
 * classes où la même matière est enseignée — demande du client, distincte du
 * partage automatique par enseignant+niveau (BUG1).
 */
class ProgressionCopieEntreClassesTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin_ecole', 'guard_name' => 'web']);
        $role->syncPermissions(RolePermissionSeeder::permissionsDuRole('admin_ecole'));

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('admin_ecole');
    }

    private function enseignant(string $nom): User
    {
        $fonction = FonctionReferentiel::firstOrCreate(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
        $user = User::create([
            'name' => $nom, 'email' => strtolower(str_replace(' ', '.', $nom)).'@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    private function classeMatiere(string $nomClasse, User $prof, string $matiere = 'Maths'): ClasseMatiere
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => $nomClasse]);
        $m = Matiere::firstOrCreate(['school_id' => $this->school->id, 'nom' => $matiere], ['statut' => 'actif']);

        return ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $m->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);
    }

    public function test_copier_une_fiche_vers_une_autre_classe_enseignant_la_meme_matiere(): void
    {
        $profA = $this->enseignant('Prof A');
        $profB = $this->enseignant('Prof B');
        $source = $this->classeMatiere('5e A', $profA);
        $cible = $this->classeMatiere('5e B', $profB);

        ProgressionColonne::create(['classe_matiere_id' => $source->id, 'libelle' => 'Observations', 'ordre' => 1]);
        $module = ProgressionItem::create(['classe_matiere_id' => $source->id, 'type' => 'module', 'titre' => 'Module 1', 'ordre' => 1]);
        ProgressionItem::create([
            'classe_matiere_id' => $source->id, 'parent_id' => $module->id, 'type' => 'lecon',
            'titre' => 'Fractions', 'ordre' => 1, 'date_realisee' => '2026-09-01',
        ]);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$source->id}/progression/copier", ['classe_matiere_ids' => [$cible->id]])
            ->assertOk();

        $this->assertSame(['5e B'], $reponse->json('data.copiees'));
        $this->assertSame([], $reponse->json('data.ignorees'));

        $this->assertDatabaseHas('progression_colonnes', ['classe_matiere_id' => $cible->id, 'libelle' => 'Observations']);
        $moduleCopie = ProgressionItem::where('classe_matiere_id', $cible->id)->where('type', 'module')->first();
        $this->assertNotNull($moduleCopie);
        $this->assertSame('Module 1', $moduleCopie->titre);

        $leconCopiee = ProgressionItem::where('classe_matiere_id', $cible->id)->where('type', 'lecon')->first();
        $this->assertSame('Fractions', $leconCopiee->titre);
        $this->assertSame($moduleCopie->id, $leconCopiee->parent_id);
        // Le déroulé réel de la classe source ne se copie pas : la copie
        // repart vierge de tout marqueur "déjà traité".
        $this->assertNull($leconCopiee->date_realisee);
    }

    public function test_une_classe_qui_a_deja_une_fiche_est_ignoree_pas_ecrasee(): void
    {
        $profA = $this->enseignant('Prof A');
        $profB = $this->enseignant('Prof B');
        $source = $this->classeMatiere('5e A', $profA);
        $cible = $this->classeMatiere('5e B', $profB);

        ProgressionItem::create(['classe_matiere_id' => $source->id, 'type' => 'lecon', 'titre' => 'Fractions', 'ordre' => 1]);
        ProgressionItem::create(['classe_matiere_id' => $cible->id, 'type' => 'lecon', 'titre' => 'Leçon déjà saisie', 'ordre' => 1]);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$source->id}/progression/copier", ['classe_matiere_ids' => [$cible->id]])
            ->assertOk();

        $this->assertSame([], $reponse->json('data.copiees'));
        $this->assertSame(['5e B'], $reponse->json('data.ignorees'));
        $this->assertSame(1, ProgressionItem::where('classe_matiere_id', $cible->id)->count());
        $this->assertDatabaseHas('progression_items', ['classe_matiere_id' => $cible->id, 'titre' => 'Leçon déjà saisie']);
    }

    public function test_les_cibles_proposees_excluent_une_matiere_differente(): void
    {
        $profA = $this->enseignant('Prof A');
        $source = $this->classeMatiere('5e A', $profA, 'Maths');
        $this->classeMatiere('5e B', $profA, 'Anglais');

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/classe-matieres/{$source->id}/progression/cibles-copie")
            ->assertOk();

        $this->assertCount(0, $reponse->json('data'));
    }

    public function test_un_enseignant_sans_le_privilege_ne_peut_pas_copier(): void
    {
        $profA = $this->enseignant('Prof A');
        $profB = $this->enseignant('Prof B');
        Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web'])
            ->syncPermissions(RolePermissionSeeder::permissionsDuRole('enseignant'));
        $profA->assignRole('enseignant');

        $source = $this->classeMatiere('5e A', $profA);
        $cible = $this->classeMatiere('5e B', $profB);

        $this->actingAs($profA, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$source->id}/progression/copier", ['classe_matiere_ids' => [$cible->id]])
            ->assertForbidden();
    }
}
