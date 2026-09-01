<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Un enseignant ne saisit que dans le trimestre actif ; la direction, elle,
 * peut encore corriger un trimestre déjà clos (rattrapage, erreur de saisie
 * découverte après coup) — cf. `User::peutSaisirHorsTrimestreActif()`.
 */
class NotesTrimestreActifTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Trimestre $trimestreActif;

    private Trimestre $trimestreClos;

    private Sequence $sequenceActive;

    private Sequence $sequenceClose;

    private User $admin;

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

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $this->trimestreClos = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => false,
        ]);
        $this->trimestreActif = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 2', 'ordre' => 2,
            'date_debut' => '2027-01-05', 'date_fin' => '2027-03-27', 'is_active' => true,
        ]);

        $this->sequenceClose = Sequence::create([
            'trimestre_id' => $this->trimestreClos->id, 'libelle' => 'Séquence 1', 'ordre' => 1,
        ]);
        $this->sequenceActive = Sequence::create([
            'trimestre_id' => $this->trimestreActif->id, 'libelle' => 'Séquence 1', 'ordre' => 1,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function enseignant(string $nom, string $email): User
    {
        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant',
        ]);
        $fonction->synchroniserPermissions(RolePermissionSeeder::ROLE_PERMISSIONS['enseignant']);

        $user = User::create([
            'name' => $nom, 'email' => $email, 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    // ----------------------------------------------------------- Secondaire

    public function test_un_enseignant_peut_noter_le_trimestre_actif(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e A']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE UN', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques', 'statut' => 'actif']);
        $prof = $this->enseignant('Prof Math', 'prof.math@test.local');
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);

        $this->actingAs($prof, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$classeMatiere->id}/notes", [
                'sequence_id' => $this->sequenceActive->id,
                'notes' => [['eleve_id' => $eleve->id, 'valeur' => 15]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $classeMatiere->id,
            'sequence_id' => $this->sequenceActive->id,
        ]);
    }

    public function test_un_enseignant_ne_peut_pas_noter_un_trimestre_clos(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e B']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE DEUX', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Français', 'statut' => 'actif']);
        $prof = $this->enseignant('Prof Français', 'prof.francais@test.local');
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);

        $this->actingAs($prof, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$classeMatiere->id}/notes", [
                'sequence_id' => $this->sequenceClose->id,
                'notes' => [['eleve_id' => $eleve->id, 'valeur' => 15]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('notes', [
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $classeMatiere->id,
        ]);
    }

    public function test_la_direction_peut_corriger_un_trimestre_clos(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e C']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE TROIS', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Histoire', 'statut' => 'actif']);
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'statut' => 'actif',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-matieres/{$classeMatiere->id}/notes", [
                'sequence_id' => $this->sequenceClose->id,
                'notes' => [['eleve_id' => $eleve->id, 'valeur' => 12]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $classeMatiere->id,
            'sequence_id' => $this->sequenceClose->id,
        ]);
    }

    // ---------------------------------------------------- Primaire/maternelle

    private function classeCompetencePrimaire(User $titulaire): ClasseCompetence
    {
        $classe = Classe::create([
            'school_id' => $this->school->id, 'nom' => 'CE1-A', 'titulaire_id' => $titulaire->personnel->id,
        ]);
        $competence = Competence::create([
            'school_id' => $this->school->id, 'label_fr' => 'Langue et communication',
            'notation' => 20, 'evalue_pratique' => false,
            'repartition_volets' => ['oral' => 10, 'ecrit' => 5, 'savoir_etre' => 5],
        ]);

        return ClasseCompetence::create(['classe_id' => $classe->id, 'competence_id' => $competence->id]);
    }

    public function test_un_titulaire_peut_noter_le_trimestre_actif_au_primaire(): void
    {
        $titulaire = $this->enseignant('Titulaire CE1', 'titulaire.ce1@test.local');
        $attribution = $this->classeCompetencePrimaire($titulaire);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $attribution->classe_id,
            'nom_complet' => 'ELEVE UN', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->actingAs($titulaire, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id, 'sequence_id' => $this->sequenceActive->id,
                    'composante' => 'oral', 'valeur' => 8,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_competence_id' => $attribution->id,
            'sequence_id' => $this->sequenceActive->id,
        ]);
    }

    public function test_un_titulaire_ne_peut_pas_noter_un_trimestre_clos_au_primaire(): void
    {
        $titulaire = $this->enseignant('Titulaire CE2', 'titulaire.ce2@test.local');
        $attribution = $this->classeCompetencePrimaire($titulaire);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $attribution->classe_id,
            'nom_complet' => 'ELEVE DEUX', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->actingAs($titulaire, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id, 'sequence_id' => $this->sequenceClose->id,
                    'composante' => 'oral', 'valeur' => 8,
                ]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('notes', [
            'eleve_id' => $eleve->id, 'classe_competence_id' => $attribution->id,
        ]);
    }

    public function test_la_direction_peut_corriger_un_trimestre_clos_au_primaire(): void
    {
        $titulaire = $this->enseignant('Titulaire CM1', 'titulaire.cm1@test.local');
        $attribution = $this->classeCompetencePrimaire($titulaire);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $attribution->classe_id,
            'nom_complet' => 'ELEVE TROIS', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/classe-competences/{$attribution->id}/notes-primaire", [
                'notes' => [[
                    'eleve_id' => $eleve->id, 'sequence_id' => $this->sequenceClose->id,
                    'composante' => 'oral', 'valeur' => 8,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notes', [
            'eleve_id' => $eleve->id, 'classe_competence_id' => $attribution->id,
            'sequence_id' => $this->sequenceClose->id,
        ]);
    }
}
