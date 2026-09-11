<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Services\MatiereFusionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fusion de deux matières en doublon (même contenu, orthographe différente) :
 * l'écran Matières laisse en sélectionner deux et choisir laquelle garder.
 */
class MatiereFusionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classeA;

    private Classe $classeB;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $niveau = Niveau::create(['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'Secondary']);
        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $this->classeA = Classe::create([
            'school_id' => $this->school->id, 'niveau_id' => $niveau->id, 'nom' => '6ème A',
        ]);
        $this->classeB = Classe::create([
            'school_id' => $this->school->id, 'niveau_id' => $niveau->id, 'nom' => '6ème B',
        ]);
    }

    /** Seule classeA enseigne les deux matières : sur classeB, rien à réconcilier, la classe suit simplement la matière conservée. */
    public function test_une_affectation_sans_conflit_est_simplement_deplacee(): void
    {
        $conservee = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Law and government', 'statut' => 'actif']);
        $supprimee = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Law and gouvernment', 'statut' => 'actif']);

        $affectationSupprimee = ClasseMatiere::create(['classe_id' => $this->classeB->id, 'matiere_id' => $supprimee->id, 'coefficient' => 2]);

        $resultat = (new MatiereFusionService)->fusionner($conservee, $supprimee);

        $this->assertSame(1, $resultat['classes_deplacees']);
        $this->assertSame(0, $resultat['classes_fusionnees']);
        $this->assertSame(0, $resultat['notes_ignorees']);

        $this->assertSame($conservee->id, $affectationSupprimee->refresh()->matiere_id);
        $this->assertNull(Matiere::find($supprimee->id));
    }

    /** classeA enseigne les deux : les deux affectations doivent se réconcilier en une seule. */
    public function test_deux_affectations_pour_la_meme_classe_fusionnent(): void
    {
        $conservee = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Law and government', 'statut' => 'actif']);
        $supprimee = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Law and gouvernment', 'statut' => 'actif']);

        $cmConservee = ClasseMatiere::create(['classe_id' => $this->classeA->id, 'matiere_id' => $conservee->id, 'coefficient' => 2]);
        $cmSupprimee = ClasseMatiere::create(['classe_id' => $this->classeA->id, 'matiere_id' => $supprimee->id, 'coefficient' => 2]);

        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classeA->id, 'nom_complet' => 'Test Élève',
            'matricule' => 'E001', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $trimestre = Trimestre::create([
            'annee_scolaire_id' => $this->annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-15',
        ]);
        $sequence = Sequence::create(['trimestre_id' => $trimestre->id, 'libelle' => 'Séquence 1', 'ordre' => 1]);

        // Note en conflit : même élève, même séquence, des deux côtés — celle
        // de la matière conservée doit l'emporter.
        Note::create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $cmConservee->id, 'sequence_id' => $sequence->id, 'composante' => 'unique', 'valeur' => 15]);
        $noteEnTrop = Note::create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $cmSupprimee->id, 'sequence_id' => $sequence->id, 'composante' => 'unique', 'valeur' => 8]);

        EmploiDuTemps::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classeA->id, 'classe_matiere_id' => $cmSupprimee->id,
            'jour' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00',
        ]);

        $resultat = (new MatiereFusionService)->fusionner($conservee, $supprimee);

        $this->assertSame(0, $resultat['classes_deplacees']);
        $this->assertSame(1, $resultat['classes_fusionnees']);
        $this->assertSame(1, $resultat['notes_ignorees']);

        $this->assertNull(Note::find($noteEnTrop->id));
        $this->assertSame(15.0, (float) Note::where('classe_matiere_id', $cmConservee->id)->value('valeur'));

        // Le créneau d'emploi du temps a rejoint l'affectation conservée.
        $this->assertSame($cmConservee->id, EmploiDuTemps::first()->classe_matiere_id);

        // L'affectation en doublon et la matière en trop ont disparu.
        $this->assertNull(ClasseMatiere::find($cmSupprimee->id));
        $this->assertNull(Matiere::find($supprimee->id));
        $this->assertNotNull(ClasseMatiere::find($cmConservee->id));
    }

    public function test_l_endpoint_refuse_de_fusionner_deux_ecoles_differentes(): void
    {
        $autreEcole = School::create(['name' => 'Autre', 'code' => 'AU', 'type' => 'secondaire', 'is_active' => true]);
        $conservee = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Anglais', 'statut' => 'actif']);
        $supprimee = Matiere::create(['school_id' => $autreEcole->id, 'nom' => 'English', 'statut' => 'actif']);

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/matieres/fusionner', ['conservee_id' => $conservee->id, 'supprimee_id' => $supprimee->id])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNotNull(Matiere::find($supprimee->id));
    }

    public function test_l_endpoint_fusionne_avec_succes(): void
    {
        $conservee = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Law and government', 'statut' => 'actif']);
        $supprimee = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Law and gouvernment', 'statut' => 'actif']);

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/matieres/fusionner', ['conservee_id' => $conservee->id, 'supprimee_id' => $supprimee->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(Matiere::find($supprimee->id));
    }

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Root',
            'email' => 'root@test.local',
            'password' => 'password',
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        return $user->fresh();
    }
}
