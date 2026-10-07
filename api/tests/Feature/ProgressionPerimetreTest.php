<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\ProgressionItem;
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
        // La progression se consulte avec `notes.view` depuis c0353f3.
        $fonction->synchroniserPermissions(['notes.view', 'pedagogie.view', 'progression.update']);

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

    /** @return array{0: User, 1: ClasseMatiere} */
    private function affectationDe(string $nom): array
    {
        $prof = $this->enseignant($nom);
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'FORM 4']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Biology']);

        return [$prof, ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'coefficient' => 1, 'statut' => 'actif',
        ])];
    }

    /**
     * Sans ce verrou, toute la règle de validation des séances se contourne
     * depuis l'éditeur de progression : l'enseignant renseigne « Date
     * Taught » à la main et sa leçon compte comme réalisée — sans séance,
     * sans appel et sans preuve de présence.
     */
    public function test_l_enseignant_ne_peut_pas_marquer_une_lecon_realisee_depuis_la_progression(): void
    {
        [$prof, $classeMatiere] = $this->affectationDe('Munyah Guilienne');

        $this->actingAs($prof, 'sanctum')
            ->putJson("/api/v1/classe-matieres/{$classeMatiere->id}/progression", [
                'items' => [[
                    'type' => 'lecon', 'titre' => 'Photosynthese',
                    'date_realisee' => '2026-09-02',
                ]],
            ])
            ->assertOk();

        $this->assertNull(ProgressionItem::where('classe_matiere_id', $classeMatiere->id)->sole()->date_realisee);
    }

    /** Une date déjà posée par une déclaration ne doit pas être effacée par une simple édition du programme. */
    public function test_l_edition_du_programme_preserve_la_date_posee_par_la_declaration(): void
    {
        [$prof, $classeMatiere] = $this->affectationDe('Munyah Guilienne');

        $lecon = ProgressionItem::create([
            'classe_matiere_id' => $classeMatiere->id, 'type' => 'lecon',
            'titre' => 'Photosynthese', 'ordre' => 1, 'date_realisee' => '2026-09-02',
        ]);

        $this->actingAs($prof, 'sanctum')
            ->putJson("/api/v1/classe-matieres/{$classeMatiere->id}/progression", [
                'items' => [[
                    'id' => $lecon->id, 'type' => 'lecon', 'titre' => 'Photosynthese (revu)',
                    'date_realisee' => null,
                ]],
            ])
            ->assertOk();

        $lecon->refresh();
        $this->assertSame('Photosynthese (revu)', $lecon->titre);
        $this->assertSame('2026-09-02', $lecon->date_realisee?->toDateString());
    }

    /** La direction, déjà dispensée de la preuve, garde la main pour corriger. */
    public function test_la_direction_peut_corriger_la_date_de_realisation(): void
    {
        [, $classeMatiere] = $this->affectationDe('Munyah Guilienne');

        $admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/classe-matieres/{$classeMatiere->id}/progression", [
                'items' => [[
                    'type' => 'lecon', 'titre' => 'Photosynthese',
                    'date_realisee' => '2026-09-02',
                ]],
            ])
            ->assertOk();

        $this->assertSame(
            '2026-09-02',
            ProgressionItem::where('classe_matiere_id', $classeMatiere->id)->sole()->date_realisee?->toDateString(),
        );
    }
}
