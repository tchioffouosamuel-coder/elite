<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Matiere;
use App\Models\ProgressionItem;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fiche de progression : saisie directe et import du classeur de
 * l'établissement.
 *
 * L'enseignant ne crée plus ses leçons avant de les préparer — une ligne de
 * progression porte les seize champs de la fiche.
 */
class ProgressionFicheTest extends TestCase
{
    use RefreshDatabase;

    private ClasseMatiere $affectation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create([
            'name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);

        $classe = Classe::create([
            'school_id' => $school->id, 'annee_scolaire_id' => $annee->id, 'nom' => 'ACCOUNTING 1',
        ]);

        $matiere = Matiere::create(['school_id' => $school->id, 'nom' => 'Accounting']);

        $this->affectation = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 1,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    /**
     * Reproduit le gabarit de l'établissement : cartouche sur cinq lignes,
     * en-têtes en ligne 6, données ensuite.
     *
     * @param  list<array<string, string>>  $lignes
     */
    private function classeur(array $lignes): UploadedFile
    {
        $entetes = [
            'A' => 'TERM', 'B' => 'MONTH', 'C' => 'WEEK', 'D' => 'Dates',
            'E' => 'EXPECTED LEARNING OUTCOMES', 'F' => 'TOPIC', 'G' => 'lesson',
            'H' => 'COMPETENCE', 'I' => "Digital\nPractical\nnormal",
            'J' => 'Stages of the lesson', 'K' => 'ENTRY BEHAVIOUR', 'L' => 'TEACHING AIDS',
            'M' => 'TEACHING LEARNING STRATEGIES', 'N' => 'REFERENCES',
            'O' => 'Stage: Introduction', 'P' => 'Stage: presentation', 'Q' => 'Stage: Conclusion',
            'S' => 'MAIN POINTS OF MATTER', 'T' => "LEARNERS’ ACTIVITIES",
            'U' => "FACILITATOR’S ACTIVITIES", 'V' => 'Start time', 'W' => 'Stop time',
            'X' => 'Duration', 'Y' => 'Reserch questions', 'Z' => 'Role call', 'AA' => 'Visa',
        ];

        $tableur = new Spreadsheet;
        $feuille = $tableur->getActiveSheet();

        $feuille->setCellValue('B1', 'Elites Bilingual nursery and primary school');
        $feuille->setCellValue('B2', 'INDIVIDUAL LESSON NOTE');

        foreach ($entetes as $colonne => $libelle) {
            $feuille->setCellValue($colonne.'6', $libelle);
        }

        foreach (array_values($lignes) as $index => $ligne) {
            foreach ($ligne as $colonne => $valeur) {
                $feuille->setCellValue($colonne.(7 + $index), $valeur);
            }
        }

        // Une seconde feuille, comme le vrai classeur : l'import ne doit lire
        // que la première.
        $tableur->createSheet()->setTitle('Subjects per staff')->setCellValue('A1', 'Nom');

        $chemin = tempnam(sys_get_temp_dir(), 'prog').'.xlsx';
        (new Xlsx($tableur))->save($chemin);

        return new UploadedFile($chemin, 'progression.xlsx', null, null, true);
    }

    private function importer(UploadedFile $fichier): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->post(
            "/api/v1/classe-matieres/{$this->affectation->id}/progression/import",
            ['fichier' => $fichier],
        );
    }

    // ------------------------------------------------------------- Import

    public function test_l_import_cree_les_lecons_de_la_feuille(): void
    {
        $fichier = $this->classeur([
            ['A' => '1st term', 'B' => 'Oct', 'C' => 'W1', 'F' => 'Quantities', 'G' => 'Lesson 1'],
            ['A' => '1st term', 'B' => 'Oct', 'C' => 'W2', 'F' => 'Balances', 'G' => 'Lesson 2'],
        ]);

        $this->importer($fichier)
            ->assertOk()
            ->assertJsonPath('data.creees', 2);

        $this->assertSame(2, ProgressionItem::where('type', 'lecon')->count());
    }

    /** Les seize champs de la fiche arrivent en une passe. */
    public function test_l_import_remplit_les_champs_de_la_fiche(): void
    {
        $fichier = $this->classeur([[
            'E' => 'The student should count',
            'F' => 'Quantities',
            'G' => 'Lesson 1',
            'H' => 'Compter jusqu\'à 100',
            'I' => 'Practical',
            'J' => 'Trois étapes',
            'K' => 'Sait compter jusqu\'à 20',
            'L' => 'Jetons',
            'M' => 'Travail de groupe',
            'N' => 'Manuel p. 12',
            'O' => 'Rappel',
            'P' => 'Exercices',
            'Q' => 'Synthèse',
            'S' => 'La dizaine',
            'T' => 'Manipulent les jetons',
            'U' => 'Guide et corrige',
        ]]);

        $this->importer($fichier)->assertOk();

        $lecon = ProgressionItem::where('type', 'lecon')->firstOrFail();

        $this->assertSame('The student should count', $lecon->expected_learning_outcomes);
        $this->assertSame('Quantities', $lecon->topic);
        $this->assertSame('Lesson 1', $lecon->lesson);
        $this->assertSame('practical', $lecon->mode);
        $this->assertSame('Trois étapes', $lecon->stages_of_lesson);
        $this->assertSame('Jetons', $lecon->teaching_aids);
        $this->assertSame('Rappel', $lecon->introduction);
        $this->assertSame('Synthèse', $lecon->conclusion);
        $this->assertSame('La dizaine', $lecon->main_points);
        $this->assertSame('Manipulent les jetons', $lecon->learners_activities);
        $this->assertSame('Guide et corrige', $lecon->facilitators_activities);
    }

    public function test_l_import_reprend_les_reperes_de_calendrier(): void
    {
        $fichier = $this->classeur([[
            'A' => '1st term', 'B' => 'Oct', 'C' => 'W3', 'D' => '15/10/2026',
            'F' => 'Quantities', 'G' => 'Lesson 1',
        ]]);

        $this->importer($fichier)->assertOk();

        $lecon = ProgressionItem::where('type', 'lecon')->firstOrFail();

        $this->assertSame('1st term', $lecon->term);
        $this->assertSame('Oct', $lecon->mois);
        $this->assertSame('W3', $lecon->semaine);
        $this->assertSame('2026-10-15', $lecon->date_prevue?->toDateString());
    }

    /** Le cœur de la règle : une saisie faite à l'écran survit à l'import. */
    public function test_l_import_complete_sans_ecraser_la_saisie_existante(): void
    {
        $existante = ProgressionItem::create([
            'classe_matiere_id' => $this->affectation->id,
            'type' => 'lecon',
            'titre' => 'Lesson 1',
            'ordre' => 1,
            'topic' => 'Quantities',
            'lesson' => 'Lesson 1',
            'teaching_aids' => 'Ce que j\'ai saisi moi-même',
        ]);

        $fichier = $this->classeur([[
            'F' => 'Quantities', 'G' => 'Lesson 1',
            'L' => 'Ce que dit le fichier',
            'N' => 'Manuel p. 12',
        ]]);

        $this->importer($fichier)
            ->assertOk()
            ->assertJsonPath('data.creees', 0)
            ->assertJsonPath('data.completees', 1);

        $existante->refresh();

        // Le champ déjà rempli est conservé…
        $this->assertSame('Ce que j\'ai saisi moi-même', $existante->teaching_aids);
        // …et le champ vide est complété.
        $this->assertSame('Manuel p. 12', $existante->references);
    }

    /** Les lignes vides pré-formatées du gabarit ne créent pas de leçons. */
    public function test_les_lignes_sans_topic_ni_lecon_sont_ignorees(): void
    {
        $fichier = $this->classeur([
            ['A' => '1st term', 'B' => 'Oct'],
            ['F' => 'Quantities', 'G' => 'Lesson 1'],
            ['A' => '1st term'],
        ]);

        $this->importer($fichier)
            ->assertOk()
            ->assertJsonPath('data.creees', 1);

        $this->assertSame(1, ProgressionItem::where('type', 'lecon')->count());
    }

    /** La colonne mode se saisit à la main : les abrégés doivent passer. */
    public function test_le_mode_est_reconnu_dans_ses_variantes(): void
    {
        $fichier = $this->classeur([
            ['F' => 'A', 'G' => 'L1', 'I' => 'Digital'],
            ['F' => 'B', 'G' => 'L2', 'I' => 'prat.'],
            ['F' => 'C', 'G' => 'L3', 'I' => 'Normal'],
            ['F' => 'D', 'G' => 'L4', 'I' => 'zzz'],
        ]);

        $this->importer($fichier)->assertOk();

        $modes = ProgressionItem::where('type', 'lecon')->orderBy('ordre')->pluck('mode', 'topic');

        $this->assertSame('digital', $modes['A']);
        $this->assertSame('practical', $modes['B']);
        $this->assertSame('normal', $modes['C']);
        // Un libellé illisible laisse le champ vide plutôt que de bloquer la ligne.
        $this->assertNull($modes['D']);
    }

    /**
     * Une feuille remplie se termine comme le gabarit : des lignes vides, puis
     * les signatures. Celles-ci tombent sous des colonnes du tableau — « The
     * dean of studies » sous TOPIC — et créeraient une leçon à leur nom.
     */
    public function test_le_pied_de_page_de_signatures_n_est_pas_une_lecon(): void
    {
        $lignes = [
            ['A' => '1st term', 'F' => 'Quantities', 'G' => 'Lesson 1'],
            ['A' => '1st term', 'F' => 'Balances', 'G' => 'Lesson 2'],
        ];

        // Les lignes pré-formatées laissées vides entre le tableau et le pied.
        $lignes = array_merge($lignes, array_fill(0, 11, ['X' => '']));
        $lignes[] = ['B' => 'The teacher', 'F' => 'The dean of studies', 'S' => 'The head teacher'];

        $this->importer($this->classeur($lignes))
            ->assertOk()
            ->assertJsonPath('data.creees', 2);

        $titres = ProgressionItem::where('type', 'lecon')->pluck('topic')->all();

        $this->assertSame(['Quantities', 'Balances'], $titres);
    }

    /**
     * Le gabarit réel de l'établissement, tel qu'il circule entre enseignants.
     *
     * Le fichier vierge ne porte aucune leçon : ce que ce test garantit, c'est
     * que ses en-têtes sont tous reconnus et qu'aucune de ses dizaines de
     * lignes pré-formatées ne crée de leçon fantôme.
     */
    public function test_le_gabarit_reel_est_lu_sans_creer_de_lecon_fantome(): void
    {
        $gabarit = base_path('tests/Fixtures/progression-sheet.xlsx');

        $this->importer(new UploadedFile($gabarit, 'progression.xlsx', null, null, true))
            ->assertOk()
            ->assertJsonPath('data.creees', 0);

        // Le pied de page de signatures ne doit pas passer pour une leçon.
        $this->assertSame(0, ProgressionItem::count());
    }

    // ------------------------------------------------------- Saisie directe

    /** La progression enregistre la fiche : plus d'étape « Préparer » séparée. */
    public function test_la_progression_enregistre_les_champs_de_la_fiche(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/classe-matieres/{$this->affectation->id}/progression", [
                'items' => [[
                    'type' => 'lecon',
                    'titre' => 'Lesson 1',
                    'topic' => 'Quantities',
                    'lesson' => 'Lesson 1',
                    'competence' => 'Compter jusqu\'à 100',
                    'mode' => 'normal',
                    'main_points' => 'La dizaine',
                    'learners_activities' => 'Manipulent',
                    'facilitators_activities' => 'Guide',
                ]],
            ])
            ->assertOk();

        $lecon = ProgressionItem::where('type', 'lecon')->firstOrFail();

        $this->assertSame('Quantities', $lecon->topic);
        $this->assertSame('normal', $lecon->mode);
        $this->assertSame('La dizaine', $lecon->main_points);
        $this->assertSame('Guide', $lecon->facilitators_activities);
    }
}
