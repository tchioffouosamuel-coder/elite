<?php

namespace Tests\Feature;

use App\Exports\RemunerationTemplateExport;
use App\Imports\RemunerationImport;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Modèle et import des rémunérations : le modèle liste le personnel en poste
 * sur une feuille dédiée pour alimenter la liste déroulante Nom de la
 * feuille de saisie, et l'import rapproche chaque ligne par nom complet.
 */
class RemunerationImportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Personnel $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'primaire', 'is_active' => true]);
        $this->agent = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'MBARGA PAUL', 'sexe' => 'M', 'statut' => 'actif',
        ]);
    }

    public function test_le_modele_porte_deux_feuilles_et_la_liste_des_agents(): void
    {
        $noms = Personnel::where('school_id', $this->school->id)->pluck('nom_complet');

        $binaire = Excel::raw(new RemunerationTemplateExport($noms), ExcelFormat::XLSX);
        $chemin = tempnam(sys_get_temp_dir(), 'modele').'.xlsx';
        file_put_contents($chemin, $binaire);

        $classeur = IOFactory::load($chemin);

        $this->assertSame(['Liste', 'Import'], $classeur->getSheetNames());
        $this->assertSame('MBARGA PAUL', $classeur->getSheetByName('Liste')->getCell('A2')->getValue());
        $this->assertSame('Nom complet', $classeur->getSheetByName('Import')->getCell('A1')->getValue());

        // La colonne Nom porte bien une liste déroulante référençant la feuille Liste.
        $validation = $classeur->getSheetByName('Import')->getCell('A2')->getDataValidation();
        $this->assertStringContainsString('Liste!', $validation->getFormula1());
    }

    private function fichierImport(array $lignes): UploadedFile
    {
        $feuille = (new Spreadsheet)->getActiveSheet();

        $feuille->fromArray([
            ['Nom complet', 'Date effet', 'Mode', 'Salaire de base', 'Taux horaire', 'Prime anciennete', 'Prime communication', 'Prime transport', 'Prime recherche', 'Prime performance', 'Categorie'],
            ...$lignes,
        ], null, 'A1');

        $chemin = tempnam(sys_get_temp_dir(), 'remimport').'.xlsx';
        (new Xlsx($feuille->getParent()))->save($chemin);

        return new UploadedFile($chemin, 'remunerations.xlsx', null, null, true);
    }

    public function test_importe_une_remuneration_mensuelle_rapprochee_par_nom(): void
    {
        $import = new RemunerationImport($this->school->id);
        Excel::import($import, $this->fichierImport([
            ['MBARGA PAUL', '2026-09-01', 'mensuel', 250000, null, 15000, 10000, 20000, null, null, '5C'],
        ]));

        $this->assertSame(1, $import->importedCount);
        $this->assertCount(0, $import->failures());

        $remuneration = Remuneration::where('personnel_id', $this->agent->id)->firstOrFail();
        $this->assertSame('mensuel', $remuneration->mode);
        $this->assertSame(250000, $remuneration->salaire_base);
        $this->assertSame(15000, $remuneration->prime_anciennete);
        $this->assertNull($remuneration->taux_horaire);
        $this->assertSame('5C', $remuneration->categorie);
    }

    public function test_le_mode_horaire_efface_le_salaire_et_les_primes(): void
    {
        $import = new RemunerationImport($this->school->id);
        Excel::import($import, $this->fichierImport([
            ['MBARGA PAUL', '2026-09-01', 'horaire', 250000, 3000, 15000, null, null, null, null, null],
        ]));

        $remuneration = Remuneration::where('personnel_id', $this->agent->id)->firstOrFail();
        $this->assertSame('horaire', $remuneration->mode);
        $this->assertSame(3000, $remuneration->taux_horaire);
        $this->assertSame(0, $remuneration->salaire_base);
        $this->assertSame(0, $remuneration->prime_anciennete);
    }

    public function test_un_nom_sans_correspondance_est_signale_sans_bloquer_les_autres(): void
    {
        $import = new RemunerationImport($this->school->id);
        Excel::import($import, $this->fichierImport([
            ['MBARGA PAUL', '2026-09-01', 'mensuel', 250000, null, null, null, null, null, null, null],
            ['AGENT INCONNU', '2026-09-01', 'mensuel', 200000, null, null, null, null, null, null, null],
        ]));

        $this->assertSame(1, $import->importedCount);
        $this->assertSame(['AGENT INCONNU' => 1], $import->nomsNonRattaches);
        $this->assertSame(1, Remuneration::count());
    }

    public function test_le_reimport_a_la_meme_date_deffet_corrige_au_lieu_de_dupliquer(): void
    {
        $import = new RemunerationImport($this->school->id);
        Excel::import($import, $this->fichierImport([
            ['MBARGA PAUL', '2026-09-01', 'mensuel', 250000, null, null, null, null, null, null, null],
        ]));

        $rejeu = new RemunerationImport($this->school->id);
        Excel::import($rejeu, $this->fichierImport([
            ['MBARGA PAUL', '2026-09-01', 'mensuel', 300000, null, null, null, null, null, null, null],
        ]));

        $this->assertSame(0, $rejeu->importedCount);
        $this->assertSame(1, $rejeu->updatedCount);
        $this->assertSame(1, Remuneration::count());
        $this->assertSame(300000, Remuneration::first()->salaire_base);
    }
}
