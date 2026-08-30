<?php

namespace Tests\Feature;

use App\Imports\DepenseImport;
use App\Models\Depense;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import en masse de dépenses courantes : chaque ligne crée une dépense
 * (pas de rapprochement possible faute de clé naturelle), et un compte
 * comptable non reconnu retombe sur le compte par défaut plutôt que de
 * bloquer la ligne.
 */
class DepenseImportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'primaire', 'is_active' => true]);

        // Le plan comptable (614, 635…) est déjà chargé par la migration qui
        // le crée (`refondre_plan_comptable_ecole`) — pas besoin de le re-seeder.
    }

    private function fichier(array $lignes): UploadedFile
    {
        $feuille = (new Spreadsheet)->getActiveSheet();

        $feuille->fromArray([
            ['Date', 'Libelle', 'Montant', 'Mode', 'Beneficiaire', 'N Facture', 'Responsable', 'Compte comptable', 'Source', 'Statut'],
            ...$lignes,
        ], null, 'A1');

        $chemin = tempnam(sys_get_temp_dir(), 'dep').'.xlsx';
        (new Xlsx($feuille->getParent()))->save($chemin);

        return new UploadedFile($chemin, 'depenses.xlsx', null, null, true);
    }

    public function test_importe_des_depenses_et_rapproche_le_compte_comptable(): void
    {
        $import = new DepenseImport($this->school->id);
        Excel::import($import, $this->fichier([
            ['2026-09-05', 'Achat de craies', 15000, 'especes', 'Papeterie du coin', 'FAC-001', 'Économe', '614', null, null],
            ['2026-09-06', 'Facture électricité', 5000, 'mobile_money', null, null, null, '626', null, null],
        ]));

        $this->assertSame(2, $import->importedCount);
        $this->assertCount(0, $import->failures());
        $this->assertSame(2, Depense::where('school_id', $this->school->id)->count());

        $craies = Depense::where('libelle', 'Achat de craies')->firstOrFail();
        $this->assertSame(15000, $craies->montant);
        $this->assertSame('especes', $craies->mode);
        $this->assertSame('614', $craies->compte->code);
        $this->assertSame('payee', $craies->statut);

        $electricite = Depense::where('libelle', 'Facture électricité')->firstOrFail();
        $this->assertSame('626', $electricite->compte->code);
        $this->assertSame('mobile_money', $electricite->mode);
    }

    public function test_un_compte_non_reconnu_retombe_sur_le_compte_par_defaut(): void
    {
        $import = new DepenseImport($this->school->id);
        Excel::import($import, $this->fichier([
            ['2026-09-05', 'Dépense diverse', 10000, null, null, null, null, 'Compte inexistant', null, null],
        ]));

        $this->assertSame(1, $import->importedCount);
        $this->assertSame(['Dépense diverse' => 1], $import->comptesNonRattaches);

        $depense = Depense::where('libelle', 'Dépense diverse')->firstOrFail();
        $this->assertSame('614', $depense->compte->code);
    }

    public function test_le_statut_engagee_et_la_source_revenu_personnel_sont_reconnus(): void
    {
        $import = new DepenseImport($this->school->id);
        Excel::import($import, $this->fichier([
            ['2026-09-05', 'Avance sur fournitures', 20000, null, null, null, null, null, 'revenu personnel', 'engagee'],
        ]));

        $depense = Depense::where('libelle', 'Avance sur fournitures')->firstOrFail();
        $this->assertSame('revenu_personnel', $depense->source);
        $this->assertSame('engagee', $depense->statut);
    }

    public function test_reimporter_le_meme_fichier_double_les_depenses(): void
    {
        $fichier = fn () => $this->fichier([
            ['2026-09-05', 'Achat répété', 10000, null, null, null, null, null, null, null],
        ]);

        Excel::import(new DepenseImport($this->school->id), $fichier());
        Excel::import(new DepenseImport($this->school->id), $fichier());

        $this->assertSame(2, Depense::where('libelle', 'Achat répété')->count());
    }

    public function test_une_ligne_sans_libelle_ou_montant_est_ignoree(): void
    {
        $import = new DepenseImport($this->school->id);
        Excel::import($import, $this->fichier([
            ['2026-09-05', null, 10000, null, null, null, null, null, null, null],
            ['2026-09-05', 'Sans montant', null, null, null, null, null, null, null, null],
        ]));

        $this->assertSame(0, $import->importedCount);
        $this->assertSame(0, Depense::count());
    }
}
