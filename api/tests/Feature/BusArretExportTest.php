<?php

namespace Tests\Feature;

use App\Exports\BusArretExport;
use App\Imports\BusArretImport;
use App\Models\BusArret;
use App\Models\BusTrajet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * L'export des arrêts de la page Arrêts : la liste à plat (trajet + arrêt),
 * et surtout son aller-retour avec {@see BusArretImport} — un export qu'on ne
 * peut pas réimporter sans perte ne sert pas à corriger un circuit dans un
 * tableur, qui est l'usage visé.
 */
class BusArretExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $nord = BusTrajet::create(['nom' => 'Ligne Nord']);
        $sud = BusTrajet::create(['nom' => 'Ligne Sud']);

        BusArret::create([
            'trajet_id' => $nord->id,
            'nom' => 'Carrefour Obili',
            'lieu_dit' => 'Face pharmacie',
            'ordre' => 2,
            'heure_passage' => '06:45',
            'tarif_aller_simple' => 6000,
            'tarif_retour_simple' => 6500,
            'tarif_aller_retour' => 11000,
        ]);
        BusArret::create(['trajet_id' => $nord->id, 'nom' => 'Mvan', 'ordre' => 1]);
        BusArret::create(['trajet_id' => $sud->id, 'nom' => 'Biyem-Assi', 'ordre' => 1]);
    }

    public function test_export_liste_les_arrets_par_trajet_puis_par_ordre(): void
    {
        $export = new BusArretExport();

        $lignes = $export->collection()->map(fn(BusArret $a) => $export->map($a))->all();

        $this->assertSame(
            [['Ligne Nord', 'Mvan'], ['Ligne Nord', 'Carrefour Obili'], ['Ligne Sud', 'Biyem-Assi']],
            array_map(fn(array $ligne) => [$ligne[0], $ligne[1]], $lignes),
        );

        $this->assertSame(
            ['Ligne Nord', 'Carrefour Obili', 'Face pharmacie', 2, '06:45', 6000, 6500, 11000],
            $lignes[1],
        );
    }

    /** Le fichier produit, réimporté tel quel, restitue exactement les mêmes arrêts. */
    public function test_le_fichier_exporte_se_reimporte_sans_perte(): void
    {
        $export = new BusArretExport();
        $attendu = $export->collection()->map(fn(BusArret $a) => $export->map($a))->all();

        $feuille = (new Spreadsheet)->getActiveSheet();
        $feuille->fromArray([$export->headings(), ...$attendu], null, 'A1');
        $chemin = tempnam(sys_get_temp_dir(), 'arrets').'.xlsx';
        (new Xlsx($feuille->getParent()))->save($chemin);

        BusArret::query()->delete();

        $import = new BusArretImport();
        Excel::import($import, new UploadedFile($chemin, 'arrets.xlsx', null, null, true));

        $this->assertSame(3, $import->importedCount);
        $this->assertCount(0, $import->failures());
        $this->assertSame([], $import->trajetsIntrouvables);

        $relu = new BusArretExport();
        $this->assertSame($attendu, $relu->collection()->map(fn(BusArret $a) => $relu->map($a))->all());
    }
}
