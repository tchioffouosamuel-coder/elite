<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Departement;
use App\Models\Matiere;
use App\Models\ProgressionColonne;
use App\Models\ProgressionItem;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fiche de progression au format des deux gabarits de l'établissement — un
 * pour maternelle/primaire, un pour le secondaire.
 *
 * Une classe de chaque cycle est montée pour vérifier que l'écran, l'import
 * et le PDF choisissent bien le bon gabarit d'après le type d'école, sans
 * qu'on ait à le préciser explicitement.
 */
class ProgressionFicheGabaritsTest extends TestCase
{
    use RefreshDatabase;

    private ClasseMatiere $primaire;

    private ClasseMatiere $secondaire;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $ecolePrimaire = School::create([
            'name' => 'Elites Primary', 'code' => 'EBPS', 'type' => 'primaire', 'is_active' => true,
        ]);
        $ecoleSecondaire = School::create([
            'name' => 'Elites College', 'code' => 'EBTC', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $classePrimaire = Classe::create(['school_id' => $ecolePrimaire->id, 'nom' => 'CLASS 4']);
        $classeSecondaire = Classe::create(['school_id' => $ecoleSecondaire->id, 'nom' => 'FORM 3']);

        $matierePrimaire = Matiere::create(['school_id' => $ecolePrimaire->id, 'nom' => 'English Language']);

        $departement = Departement::create(['school_id' => $ecoleSecondaire->id, 'nom' => 'Electrical Engineering']);
        $matiereSecondaire = Matiere::create([
            'school_id' => $ecoleSecondaire->id, 'departement_id' => $departement->id, 'nom' => 'Electrical Installation',
        ]);

        $this->primaire = ClasseMatiere::create([
            'classe_id' => $classePrimaire->id, 'matiere_id' => $matierePrimaire->id, 'coefficient' => 1,
        ]);
        $this->secondaire = ClasseMatiere::create([
            'classe_id' => $classeSecondaire->id, 'matiere_id' => $matiereSecondaire->id, 'coefficient' => 1,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $ecolePrimaire->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function acteur(): static
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    // ---------------------------------------------------------- Le cycle

    public function test_le_programme_indique_le_cycle_de_l_affectation(): void
    {
        $this->acteur()->getJson("/api/v1/classe-matieres/{$this->primaire->id}/progression")
            ->assertOk()->assertJsonPath('data.cycle', 'primaire');

        $this->acteur()->getJson("/api/v1/classe-matieres/{$this->secondaire->id}/progression")
            ->assertOk()->assertJsonPath('data.cycle', 'secondaire');
    }

    // ------------------------------------------------------- Saisie directe

    public function test_une_lecon_primaire_enregistre_les_champs_du_gabarit(): void
    {
        $this->acteur()->putJson("/api/v1/classe-matieres/{$this->primaire->id}/progression", [
            'items' => [[
                'type' => 'lecon',
                'titre' => 'Nouns',
                'semaine' => '1',
                'duree' => '40 min',
                'topic' => 'Nouns and Their Types',
                'sous_topic' => 'Common and Proper Nouns',
                'competence' => 'Learners should be able to identify and use common and proper nouns',
                'expected_learning_outcomes' => 'Define a noun.',
                'entry_behaviour' => 'Learners already know names of people.',
                'teaching_aids' => 'Charts, Flashcards',
                'facilitators_activities' => 'Greets learners; shows pictures',
                'learners_activities' => 'Identify pictures shown',
                'assessment' => 'Oral Q&A',
                'assignment' => 'Write five common nouns',
                'remarks' => 'Went well',
            ]],
        ])->assertOk();

        $lecon = ProgressionItem::where('type', 'lecon')->firstOrFail();

        $this->assertSame('40 min', $lecon->duree);
        $this->assertSame('Common and Proper Nouns', $lecon->sous_topic);
        $this->assertSame('Learners should be able to identify and use common and proper nouns', $lecon->competence);
        $this->assertSame('Oral Q&A', $lecon->assessment);
        $this->assertSame('Write five common nouns', $lecon->assignment);
        $this->assertSame('Went well', $lecon->remarks);
    }

    public function test_une_lecon_secondaire_enregistre_les_champs_du_gabarit(): void
    {
        $this->acteur()->putJson("/api/v1/classe-matieres/{$this->secondaire->id}/progression", [
            'items' => [[
                'type' => 'lecon',
                'titre' => 'The Multimeter',
                'duree' => '3',
                'topic' => 'The Multimeter',
                'sous_topic' => 'Measurement of Voltage, Current and Resistance',
                'expected_learning_outcomes' => 'Identify the parts of a multimeter.',
                'teaching_learning_strategies' => 'Demonstration, guided practice',
            ]],
        ])->assertOk();

        $lecon = ProgressionItem::where('type', 'lecon')->firstOrFail();

        $this->assertSame('3', $lecon->duree);
        $this->assertSame('Demonstration, guided practice', $lecon->teaching_learning_strategies);
        // Competency n'existe pas sur le gabarit secondaire : rien ne l'y force.
        $this->assertNull($lecon->competence);
    }

    public function test_la_date_taught_se_distingue_de_la_date_planned(): void
    {
        $this->acteur()->putJson("/api/v1/classe-matieres/{$this->primaire->id}/progression", [
            'items' => [[
                'type' => 'lecon', 'titre' => 'Nouns',
                'date_prevue' => '2026-10-06', 'date_realisee' => '2026-10-08',
            ]],
        ])->assertOk();

        $lecon = ProgressionItem::where('type', 'lecon')->firstOrFail();

        $this->assertSame('2026-10-06', $lecon->date_prevue->toDateString());
        $this->assertSame('2026-10-08', $lecon->date_realisee->toDateString());
    }

    // -------------------------------------------------------------- Cartouche

    public function test_le_cartouche_secondaire_s_enregistre(): void
    {
        $this->acteur()
            ->putJson("/api/v1/classe-matieres/{$this->secondaire->id}/progression/cartouche", [
                'specialite' => 'Power Systems',
                'module_competence' => 'Install and maintain electrical circuits',
            ])
            ->assertOk()
            ->assertJsonPath('data.specialite', 'Power Systems')
            ->assertJsonPath('data.module_competence', 'Install and maintain electrical circuits')
            // Déduit du département de la matière, pas saisi ici.
            ->assertJsonPath('data.departement', 'Electrical Engineering');

        $this->assertSame('Power Systems', $this->secondaire->fresh()->specialite);
    }

    // ------------------------------------------------------ Colonnes libres

    public function test_les_colonnes_libres_se_definissent_et_portent_des_valeurs(): void
    {
        $colonnes = $this->acteur()
            ->putJson("/api/v1/classe-matieres/{$this->primaire->id}/progression-colonnes", [
                'colonnes' => [['libelle' => 'Vocabulaire'], ['libelle' => 'Support numérique']],
            ])
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $colonnes);
        $idVocabulaire = $colonnes[0]['id'];

        $this->acteur()->putJson("/api/v1/classe-matieres/{$this->primaire->id}/progression", [
            'items' => [[
                'type' => 'lecon', 'titre' => 'Nouns',
                'colonnes_libres' => [(string) $idVocabulaire => 'noun, proper, common'],
            ]],
        ])->assertOk();

        $lecon = ProgressionItem::where('type', 'lecon')->firstOrFail();

        $this->assertSame('noun, proper, common', $lecon->colonnes_libres[$idVocabulaire]);
    }

    public function test_plus_de_dix_colonnes_libres_est_refuse(): void
    {
        $colonnes = array_map(fn ($i) => ['libelle' => "Colonne $i"], range(1, 11));

        $this->acteur()
            ->putJson("/api/v1/classe-matieres/{$this->primaire->id}/progression-colonnes", ['colonnes' => $colonnes])
            ->assertStatus(422);

        $this->assertSame(0, ProgressionColonne::count());
    }

    // ------------------------------------------------------------- Import

    private function importer(ClasseMatiere $cm, string $fixture): \Illuminate\Testing\TestResponse
    {
        $fichier = new UploadedFile(base_path("tests/Fixtures/{$fixture}"), $fixture, null, null, true);

        return $this->acteur()->post("/api/v1/classe-matieres/{$cm->id}/progression/import", ['fichier' => $fichier]);
    }

    /** Le gabarit primaire, réel, porte une leçon d'exemple en ligne 8 (en-têtes en ligne 7). */
    public function test_l_import_primaire_lit_le_gabarit_reel(): void
    {
        $this->importer($this->primaire, 'progression-primaire.xlsx')
            ->assertOk()
            ->assertJsonPath('data.creees', 1);

        $lecon = ProgressionItem::where('type', 'lecon')->firstOrFail();

        $this->assertSame('Nouns and Their Types', $lecon->topic);
        $this->assertSame('Common and Proper Nouns', $lecon->sous_topic);
        $this->assertSame('40 min', $lecon->duree);
        $this->assertStringContainsString('common and proper nouns', (string) $lecon->competence);
    }

    /** Le gabarit secondaire, réel, porte une leçon d'exemple en ligne 10 (en-têtes en ligne 8). */
    public function test_l_import_secondaire_lit_le_gabarit_reel(): void
    {
        $this->importer($this->secondaire, 'progression-secondaire.xlsx')
            ->assertOk()
            ->assertJsonPath('data.creees', 1);

        $lecon = ProgressionItem::where('type', 'lecon')->firstOrFail();

        $this->assertSame('The Multimeter', $lecon->topic);
        $this->assertStringContainsString('Measurement of Voltage', (string) $lecon->sous_topic);
        $this->assertSame('3', $lecon->duree);
        $this->assertStringContainsString('Demonstration', (string) $lecon->teaching_learning_strategies);
    }

    /** Cœur de la règle : une saisie faite à l'écran survit à l'import. */
    public function test_l_import_complete_sans_ecraser_la_saisie_existante(): void
    {
        ProgressionItem::create([
            'classe_matiere_id' => $this->primaire->id, 'type' => 'lecon', 'titre' => 'Nouns and Their Types',
            'ordre' => 1, 'topic' => 'Nouns and Their Types', 'sous_topic' => 'Common and Proper Nouns',
            'teaching_aids' => 'Ce que j\'ai saisi moi-même',
        ]);

        $reponse = $this->importer($this->primaire, 'progression-primaire.xlsx')->assertOk();

        $this->assertSame(0, $reponse->json('data.creees'));
        $this->assertSame(1, $reponse->json('data.completees'));

        $lecon = ProgressionItem::where('type', 'lecon')->firstOrFail();

        $this->assertSame('Ce que j\'ai saisi moi-même', $lecon->teaching_aids);
        // Le champ resté vide, lui, a été complété par le fichier.
        $this->assertNotNull($lecon->expected_learning_outcomes);
    }

    /** Le format du fichier doit correspondre au cycle de l'affectation visée. */
    public function test_l_import_refuse_le_fichier_du_mauvais_cycle(): void
    {
        $this->importer($this->secondaire, 'progression-primaire.xlsx')->assertStatus(422);
        $this->importer($this->primaire, 'progression-secondaire.xlsx')->assertStatus(422);

        $this->assertSame(0, ProgressionItem::count());
    }

    // ------------------------------------------------------------------ PDF

    public function test_le_pdf_de_la_fiche_primaire_se_genere(): void
    {
        $this->acteur()->putJson("/api/v1/classe-matieres/{$this->primaire->id}/progression", [
            'items' => [['type' => 'lecon', 'titre' => 'Nouns', 'topic' => 'Nouns']],
        ])->assertOk();

        $reponse = $this->acteur()->get("/api/v1/classe-matieres/{$this->primaire->id}/progression/pdf");

        $reponse->assertOk();
        $this->assertSame('application/pdf', $reponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    public function test_le_pdf_de_la_fiche_secondaire_se_genere_meme_sans_lecon(): void
    {
        $reponse = $this->acteur()->get("/api/v1/classe-matieres/{$this->secondaire->id}/progression/pdf");

        $reponse->assertOk();
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    public function test_le_pdf_avec_des_colonnes_libres_se_genere(): void
    {
        $this->acteur()->putJson("/api/v1/classe-matieres/{$this->primaire->id}/progression-colonnes", [
            'colonnes' => [['libelle' => 'Vocabulaire']],
        ])->assertOk();

        $this->acteur()->putJson("/api/v1/classe-matieres/{$this->primaire->id}/progression", [
            'items' => [['type' => 'lecon', 'titre' => 'Nouns', 'topic' => 'Nouns']],
        ])->assertOk();

        $this->acteur()->get("/api/v1/classe-matieres/{$this->primaire->id}/progression/pdf")
            ->assertOk();
    }
}
