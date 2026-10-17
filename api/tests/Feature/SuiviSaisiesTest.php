<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\Preinscription;
use App\Models\ProgressionItem;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Point 15 de la punch-list : tableau de suivi transverse des notes
 * manquantes et des leçons en retard, pour la séquence active de l'école.
 */
class SuiviSaisiesTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private Trimestre $trimestreActif;

    private Sequence $sequenceActive;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $this->trimestreActif = Trimestre::create([
            'annee_scolaire_id' => $this->annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true,
        ]);
        $this->sequenceActive = Sequence::create([
            'trimestre_id' => $this->trimestreActif->id, 'libelle' => 'Séquence 1', 'ordre' => 1,
        ]);
        // Deuxième séquence du trimestre : ne doit jamais entrer dans les
        // comptages de la séquence active (la première).
        Sequence::create(['trimestre_id' => $this->trimestreActif->id, 'libelle' => 'Séquence 2', 'ordre' => 2]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function inscrire(Eleve $eleve): void
    {
        Preinscription::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id,
            'eleve_id' => $eleve->id, 'type' => 'existant', 'statut' => 'validee',
            'donnees_eleve' => [], 'donnees_tuteurs' => [],
        ]);
    }

    public function test_un_enseignant_nordinaire_na_pas_acces_au_suivi(): void
    {
        $prof = User::create([
            'name' => 'Prof', 'email' => 'prof@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $prof->givePermissionTo(['notes.view', 'notes.create']);

        $this->actingAs($prof, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/suivi-saisies')
            ->assertForbidden();
    }

    public function test_les_notes_et_lecons_manquantes_sont_comptees_pour_la_sequence_active(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e A']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques', 'statut' => 'actif']);
        $enseignant = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'PROF MATHS', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $enseignant->id, 'statut' => 'actif',
        ]);

        $eleve1 = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE UN', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $eleve2 = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE DEUX', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->inscrire($eleve1);
        $this->inscrire($eleve2);

        // Une seule des deux notes attendues est saisie.
        Note::create([
            'eleve_id' => $eleve1->id, 'classe_matiere_id' => $classeMatiere->id,
            'sequence_id' => $this->sequenceActive->id, 'composante' => 'unique', 'valeur' => 15,
        ]);

        // Une leçon attendue (déjà passée), pas encore donnée.
        ProgressionItem::create([
            'classe_matiere_id' => $classeMatiere->id, 'type' => 'lecon', 'titre' => 'Chap 1',
            'ordre' => 1, 'sequence_id' => $this->sequenceActive->id, 'date_prevue' => '2026-09-05',
        ]);
        // Une leçon future : ne doit pas compter dans l'attendu.
        ProgressionItem::create([
            'classe_matiere_id' => $classeMatiere->id, 'type' => 'lecon', 'titre' => 'Chap 2',
            'ordre' => 2, 'sequence_id' => $this->sequenceActive->id, 'date_prevue' => '2030-01-01',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/suivi-saisies')
            ->assertOk();

        $response->assertJsonPath('data.sequence.id', $this->sequenceActive->id);

        $ligne = collect($response->json('data.lignes'))->firstWhere('matiere_id', $matiere->id);
        $this->assertSame($classe->id, $ligne['classe_id']);
        $this->assertSame($enseignant->id, $ligne['enseignant_id']);
        $this->assertSame(2, $ligne['notes_attendues']);
        $this->assertSame(1, $ligne['notes_saisies']);
        $this->assertEquals(50.0, $ligne['notes_taux']);
        $this->assertSame(1, $ligne['lecons_attendues']);
        $this->assertSame(0, $ligne['lecons_faites']);
    }

    public function test_le_filtre_classe_id_restreint_le_resultat(): void
    {
        $classeA = Classe::create(['school_id' => $this->school->id, 'nom' => 'A']);
        $classeB = Classe::create(['school_id' => $this->school->id, 'nom' => 'B']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Français', 'statut' => 'actif']);
        ClasseMatiere::create(['classe_id' => $classeA->id, 'matiere_id' => $matiere->id, 'statut' => 'actif']);
        ClasseMatiere::create(['classe_id' => $classeB->id, 'matiere_id' => $matiere->id, 'statut' => 'actif']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson("/api/v1/suivi-saisies?classe_id={$classeA->id}")
            ->assertOk();

        $lignes = collect($response->json('data.lignes'));
        $this->assertTrue($lignes->every(fn ($l) => $l['classe_id'] === $classeA->id));
        $this->assertGreaterThan(0, $lignes->count());
    }

    public function test_le_primaire_compte_par_competence_en_agregeant_ses_matieres(): void
    {
        $this->school->update(['type' => 'primaire']);
        $titulaire = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'TITULAIRE CE1', 'sexe' => 'F', 'statut' => 'actif',
        ]);
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CE1', 'titulaire_id' => $titulaire->id]);
        $competence = Competence::create(['school_id' => $this->school->id, 'label_fr' => 'Langue et communication']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Lecture', 'competence_id' => $competence->id]);
        $attribution = ClasseCompetence::create([
            'classe_id' => $classe->id, 'competence_id' => $competence->id,
            'notation' => 20, 'evalue_pratique' => false,
            'repartition_volets' => ['oral' => 10, 'ecrit' => 10],
        ]);
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'statut' => 'actif',
        ]);

        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE PRIMAIRE', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->inscrire($eleve);

        Note::create([
            'eleve_id' => $eleve->id, 'classe_competence_id' => $attribution->id,
            'sequence_id' => $this->sequenceActive->id, 'composante' => 'oral', 'valeur' => 8,
        ]);

        ProgressionItem::create([
            'classe_matiere_id' => $classeMatiere->id, 'type' => 'lecon', 'titre' => 'Lecon 1',
            'ordre' => 1, 'sequence_id' => $this->sequenceActive->id, 'date_prevue' => '2026-09-05',
            'date_realisee' => '2026-09-05',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/suivi-saisies')
            ->assertOk();

        $ligne = collect($response->json('data.lignes'))->firstWhere('matiere_id', $competence->id);
        $this->assertSame($titulaire->id, $ligne['enseignant_id']);
        // 1 élève × 2 volets (oral, écrit) = 2 attendues, 1 saisie (oral).
        $this->assertSame(2, $ligne['notes_attendues']);
        $this->assertSame(1, $ligne['notes_saisies']);
        $this->assertSame(1, $ligne['lecons_attendues']);
        $this->assertSame(1, $ligne['lecons_faites']);
    }
}
