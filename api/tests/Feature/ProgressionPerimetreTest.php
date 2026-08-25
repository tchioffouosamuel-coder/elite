<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Un enseignant ne doit voir, dans « Progression pédagogique », que les
 * classes où il enseigne — et, dans une classe partagée, que ses propres
 * matières, pas celles de ses collègues.
 */
class ProgressionPerimetreTest extends TestCase
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
            'name' => 'Elites College', 'code' => 'EBTC', 'type' => 'secondaire', 'is_active' => true,
        ]);
    }

    private function enseignant(string $nom): User
    {
        $fonction = FonctionReferentiel::firstOrCreate(
            ['school_id' => $this->school->id, 'label_fr' => 'Enseignant'],
        );
        $fonction->synchroniserPermissions(['pedagogie.view', 'pedagogie.manage']);

        $user = User::create([
            'name' => $nom, 'email' => strtolower(str_replace(' ', '.', $nom)).'@test.local',
            'password' => 'password', 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        Personnel::create([
            'school_id' => $this->school->id,
            'user_id' => $user->id,
            'fonction_id' => $fonction->id,
            'nom_complet' => $nom,
            'sexe' => 'M',
            'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    /** Le professeur ne voit, dans la vue d'ensemble, que les classes où il intervient. */
    public function test_l_enseignant_ne_voit_que_ses_classes_dans_la_vue_d_ensemble(): void
    {
        $prof = $this->enseignant('Munyah Guilienne');
        $autreProf = $this->enseignant('Autre Prof');

        $classeAMoi = Classe::create(['school_id' => $this->school->id, 'nom' => 'ACCOUNTING 1']);
        $classePasAMoi = Classe::create(['school_id' => $this->school->id, 'nom' => 'ACT 1']);

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Accounting']);
        $autreMatiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathematics']);

        ClasseMatiere::create([
            'classe_id' => $classeAMoi->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'coefficient' => 1, 'statut' => 'actif',
        ]);
        ClasseMatiere::create([
            'classe_id' => $classePasAMoi->id, 'matiere_id' => $autreMatiere->id,
            'personnel_id' => $autreProf->personnel->id, 'coefficient' => 1, 'statut' => 'actif',
        ]);

        $reponse = $this->actingAs($prof, 'sanctum')
            ->getJson('/api/v1/progression')
            ->assertOk();

        $classes = collect($reponse->json('data'))->pluck('classe');

        $this->assertTrue($classes->contains('ACCOUNTING 1'));
        $this->assertFalse($classes->contains('ACT 1'));
    }

    /** Dans une classe partagée, il ne voit que ses matières, pas celles d'un collègue. */
    public function test_l_enseignant_ne_voit_que_ses_matieres_dans_une_classe_partagee(): void
    {
        $prof = $this->enseignant('Munyah Guilienne');
        $collegue = $this->enseignant('Collegue');

        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'FORM 3']);
        $maMatiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Accounting']);
        $saMatiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathematics']);

        ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $maMatiere->id,
            'personnel_id' => $prof->personnel->id, 'coefficient' => 1, 'statut' => 'actif',
        ]);
        $cmCollegue = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $saMatiere->id,
            'personnel_id' => $collegue->personnel->id, 'coefficient' => 1, 'statut' => 'actif',
        ]);

        $reponse = $this->actingAs($prof, 'sanctum')
            ->getJson("/api/v1/classes/{$classe->id}/progression")
            ->assertOk();

        $matieres = collect($reponse->json('data'))->pluck('matiere');

        $this->assertTrue($matieres->contains('Accounting'));
        $this->assertFalse($matieres->contains('Mathematics'));

        // Deviner l'id de l'affectation du collègue ne suffit pas non plus.
        $this->actingAs($prof, 'sanctum')
            ->getJson("/api/v1/classe-matieres/{$cmCollegue->id}/progression")
            ->assertForbidden();
    }

    /** Un compte non borné (super admin) continue de tout voir. */
    public function test_le_super_admin_voit_toutes_les_classes_et_matieres(): void
    {
        $admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $prof = $this->enseignant('Munyah Guilienne');
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'FORM 3']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Accounting']);

        ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'coefficient' => 1, 'statut' => 'actif',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/progression')
            ->assertOk()
            ->assertJsonFragment(['classe' => 'FORM 3']);
    }
}
