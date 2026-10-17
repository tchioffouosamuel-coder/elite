<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Point 23 de la punch-list : notes de séquence de l'enfant, avec tendance
 * ("progression"/"stagnation"/"baisse") par matière et globale, sans jamais
 * exposer de rang ni de moyenne de classe.
 */
class ParentNotesSequenceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    private Eleve $eleve;

    private User $userParent;

    private Sequence $sequence1;

    private Sequence $sequence2;

    private ClasseMatiere $maths;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'eleves.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'notes.view', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        $trimestre = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true,
        ]);
        $this->sequence1 = Sequence::create(['trimestre_id' => $trimestre->id, 'libelle' => 'Séquence 1', 'ordre' => 1]);
        $this->sequence2 = Sequence::create(['trimestre_id' => $trimestre->id, 'libelle' => 'Séquence 2', 'ordre' => 2]);

        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e Notes']);
        $this->eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'ELEVE NOTES', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Maths', 'statut' => 'actif']);
        $this->maths = ClasseMatiere::create([
            'classe_id' => $this->classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 4, 'statut' => 'actif',
        ]);

        $tuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Parent Notes']);
        $this->eleve->tuteurs()->attach($tuteur->id, ['is_principal' => true]);

        $this->userParent = User::create([
            'name' => 'Parent Notes', 'email' => 'parent.notes@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->userParent->assignRole('parent');
        $this->userParent->givePermissionTo(['eleves.view', 'notes.view']);
        $tuteur->update(['user_id' => $this->userParent->id]);
    }

    public function test_le_parent_voit_une_tendance_en_progression_sans_rang(): void
    {
        Note::create(['eleve_id' => $this->eleve->id, 'classe_matiere_id' => $this->maths->id, 'sequence_id' => $this->sequence1->id, 'valeur' => 10]);
        Note::create(['eleve_id' => $this->eleve->id, 'classe_matiere_id' => $this->maths->id, 'sequence_id' => $this->sequence2->id, 'valeur' => 16]);

        $reponse = $this->actingAs($this->userParent, 'sanctum')
            ->getJson("/api/v1/parent/enfants/{$this->eleve->id}/notes")
            ->assertOk();

        $matiere = $reponse->json('data.matieres.0');
        $this->assertSame('progression', $matiere['tendance']);
        $this->assertSame('progression', $reponse->json('data.tendance_generale'));
        $this->assertArrayNotHasKey('rang', $matiere);
        $this->assertArrayNotHasKey('rang_general', $reponse->json('data'));
    }

    public function test_une_baisse_de_note_est_signalee(): void
    {
        Note::create(['eleve_id' => $this->eleve->id, 'classe_matiere_id' => $this->maths->id, 'sequence_id' => $this->sequence1->id, 'valeur' => 15]);
        Note::create(['eleve_id' => $this->eleve->id, 'classe_matiere_id' => $this->maths->id, 'sequence_id' => $this->sequence2->id, 'valeur' => 8]);

        $reponse = $this->actingAs($this->userParent, 'sanctum')
            ->getJson("/api/v1/parent/enfants/{$this->eleve->id}/notes")
            ->assertOk();

        $this->assertSame('baisse', $reponse->json('data.matieres.0.tendance'));
    }

    public function test_sans_note_precedente_la_tendance_est_nulle(): void
    {
        Note::create(['eleve_id' => $this->eleve->id, 'classe_matiere_id' => $this->maths->id, 'sequence_id' => $this->sequence1->id, 'valeur' => 12]);

        $reponse = $this->actingAs($this->userParent, 'sanctum')
            ->getJson("/api/v1/parent/enfants/{$this->eleve->id}/notes")
            ->assertOk();

        $this->assertNull($reponse->json('data.matieres.0.tendance'));
    }

    public function test_un_parent_ne_peut_pas_consulter_les_notes_dun_enfant_qui_nest_pas_le_sien(): void
    {
        $autreEleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'AUTRE ELEVE', 'sexe' => 'F', 'statut' => 'actif',
        ]);

        $this->actingAs($this->userParent, 'sanctum')
            ->getJson("/api/v1/parent/enfants/{$autreEleve->id}/notes")
            ->assertNotFound();
    }
}
