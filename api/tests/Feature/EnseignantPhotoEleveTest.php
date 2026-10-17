<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Points 2 et 20 de la punch-list : un enseignant (ou un membre du personnel)
 * affecté à une classe peut prendre/téléverser la photo d'un élève de cette
 * classe, mais pas celle d'un élève d'une classe où il n'enseigne pas.
 */
class EnseignantPhotoEleveTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);
        $role->syncPermissions(RolePermissionSeeder::permissionsDuRole('enseignant'));

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
    }

    private function enseignant(string $classeAffectee): User
    {
        $fonction = FonctionReferentiel::firstOrCreate(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
        $user = User::create([
            'name' => 'Prof Photo', 'email' => 'prof.photo.'.$classeAffectee.'@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->assignRole('enseignant');
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'Prof Photo', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => $classeAffectee]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Anglais '.$classeAffectee, 'statut' => 'actif']);
        ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $user->fresh()->personnel->id, 'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    private function eleveDans(string $nomClasse): Eleve
    {
        $classe = Classe::where('school_id', $this->school->id)->where('nom', $nomClasse)->first()
            ?? Classe::create(['school_id' => $this->school->id, 'nom' => $nomClasse]);

        return Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE '.$nomClasse, 'sexe' => 'M', 'statut' => 'actif',
        ]);
    }

    public function test_un_enseignant_televerse_la_photo_dun_eleve_de_sa_classe(): void
    {
        $prof = $this->enseignant('6e Photo A');
        $eleve = $this->eleveDans('6e Photo A');

        $this->actingAs($prof, 'sanctum')
            ->post("/api/v1/eleves/{$eleve->id}/photo", [
                'photo' => UploadedFile::fake()->image('eleve.jpg', 300, 300),
            ])
            ->assertOk();

        $this->assertNotNull($eleve->fresh()->photo_path);
    }

    public function test_un_enseignant_ne_peut_pas_changer_la_photo_dun_eleve_dune_autre_classe(): void
    {
        $prof = $this->enseignant('6e Photo B');
        $eleveAilleurs = $this->eleveDans('6e Photo Autre');

        $this->actingAs($prof, 'sanctum')
            ->post("/api/v1/eleves/{$eleveAilleurs->id}/photo", [
                'photo' => UploadedFile::fake()->image('eleve.jpg', 300, 300),
            ])
            ->assertNotFound();

        $this->assertNull($eleveAilleurs->fresh()->photo_path);
    }
}
