<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Matiere;
use App\Models\ProgressionItem;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Import groupé de la fiche de progression pour toute une classe : un seul
 * classeur, une feuille par matière — le modèle vide se télécharge, se
 * remplit, se réimporte en un seul envoi plutôt que matière par matière.
 */
class ProgressionImportGroupeTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classe;

    private ClasseMatiere $anglais;

    private ClasseMatiere $maths;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create(['name' => 'Elites Primary', 'code' => 'EBPS', 'type' => 'primaire', 'is_active' => true]);
        $this->classe = Classe::create(['school_id' => $school->id, 'nom' => 'CLASS 4']);

        $anglais = Matiere::create(['school_id' => $school->id, 'nom' => 'English Language']);
        $maths = Matiere::create(['school_id' => $school->id, 'nom' => 'Mathematics']);

        $this->anglais = ClasseMatiere::create(['classe_id' => $this->classe->id, 'matiere_id' => $anglais->id, 'coefficient' => 1]);
        $this->maths = ClasseMatiere::create(['classe_id' => $this->classe->id, 'matiere_id' => $maths->id, 'coefficient' => 1]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function acteur(): static
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    public function test_le_modele_se_telecharge_avec_une_feuille_par_matiere(): void
    {
        Excel::fake();

        $this->acteur()->get("/api/v1/classes/{$this->classe->id}/progression/modele")->assertOk();

        Excel::assertDownloaded('modele-progression-class-4.xlsx', function ($export) {
            return count($export->sheets()) === 2;
        });
    }

    public function test_le_modele_est_introuvable_sans_matiere_affectee(): void
    {
        $classeVide = Classe::create(['school_id' => $this->classe->school_id, 'nom' => 'CLASS 5']);

        $this->acteur()->get("/api/v1/classes/{$classeVide->id}/progression/modele")->assertStatus(404);
    }

    /** Construit un classeur au format du gabarit primaire, en-têtes en ligne 7, une feuille par matière. */
    private function fichierGroupe(): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $entetes = ['Week', 'Date Planned', 'Date Taught', 'Duration', 'Topic', 'Sub-topic', 'Competency'];

        foreach ([
            [$this->anglais, 'Nouns and Their Types', 'Common and Proper Nouns'],
            [$this->maths, 'Addition', 'Adding Two-Digit Numbers'],
        ] as [$cm, $topic, $sousTopic]) {
            $feuille = $spreadsheet->createSheet();
            $feuille->setTitle($cm->id.' '.$cm->matiere->nom);

            foreach ($entetes as $colonne => $entete) {
                $feuille->setCellValueByColumnAndRow($colonne + 1, 7, $entete);
            }

            $feuille->setCellValueByColumnAndRow(5, 8, $topic);
            $feuille->setCellValueByColumnAndRow(6, 8, $sousTopic);
        }

        $chemin = tempnam(sys_get_temp_dir(), 'progression-groupe').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($chemin);

        return $chemin;
    }

    public function test_l_import_groupe_cree_une_lecon_par_feuille_reconnue(): void
    {
        $chemin = $this->fichierGroupe();
        $fichier = new UploadedFile($chemin, 'progression-groupe.xlsx', null, null, true);

        $reponse = $this->acteur()
            ->post("/api/v1/classes/{$this->classe->id}/progression/import", ['fichier' => $fichier])
            ->assertOk();

        $this->assertSame(2, $reponse->json('data.creees'));
        $this->assertSame(2, $reponse->json('data.matieres_importees'));
        $this->assertSame([], $reponse->json('data.feuilles_ignorees'));

        $leconAnglais = ProgressionItem::where('classe_matiere_id', $this->anglais->id)->firstOrFail();
        $this->assertSame('Common and Proper Nouns', $leconAnglais->sous_topic);

        $leconMaths = ProgressionItem::where('classe_matiere_id', $this->maths->id)->firstOrFail();
        $this->assertSame('Adding Two-Digit Numbers', $leconMaths->sous_topic);

        unlink($chemin);
    }

    public function test_une_feuille_au_titre_non_reconnu_est_ignoree_sans_bloquer_les_autres(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $feuilleValide = $spreadsheet->createSheet();
        $feuilleValide->setTitle($this->anglais->id.' English Language');
        foreach (['Week', 'Date Planned', 'Date Taught', 'Duration', 'Topic', 'Sub-topic', 'Competency'] as $colonne => $entete) {
            $feuilleValide->setCellValueByColumnAndRow($colonne + 1, 7, $entete);
        }
        $feuilleValide->setCellValueByColumnAndRow(5, 8, 'Nouns and Their Types');
        $feuilleValide->setCellValueByColumnAndRow(6, 8, 'Common and Proper Nouns');

        $feuilleInconnue = $spreadsheet->createSheet();
        $feuilleInconnue->setTitle('Feuille perso');
        $feuilleInconnue->setCellValueByColumnAndRow(1, 1, 'Notes diverses');

        $chemin = tempnam(sys_get_temp_dir(), 'progression-groupe').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($chemin);
        $fichier = new UploadedFile($chemin, 'progression-groupe.xlsx', null, null, true);

        $reponse = $this->acteur()
            ->post("/api/v1/classes/{$this->classe->id}/progression/import", ['fichier' => $fichier])
            ->assertOk();

        $this->assertSame(1, $reponse->json('data.matieres_importees'));
        $this->assertSame(['Feuille perso'], $reponse->json('data.feuilles_ignorees'));

        unlink($chemin);
    }
}
