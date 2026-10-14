<?php

namespace Tests\Feature;

use App\Imports\PersonnelImport;
use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Rapprochement du libellé de fonction avec le référentiel.
 *
 * Un établissement bilingue nomme ses fonctions dans l'une ou l'autre langue :
 * un classeur qui dit « Teacher » doit retomber sur « Enseignant », qui porte
 * déjà ses privilèges. Créer une fonction homonyme et vierge laissait l'agent
 * sans `notes.view` ni `est_enseignant` — donc sans accès à la saisie des
 * notes dans l'application mobile.
 */
class PersonnelImportFonctionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true,
        ]);

        FonctionReferentiel::create([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant', 'label_en' => 'Teacher',
        ]);
        FonctionReferentiel::create([
            'school_id' => $this->school->id, 'label_fr' => 'Économe', 'label_en' => 'Bursar',
        ]);
    }

    /**
     * @param  list<array{0: string, 1: ?string}>  $agents  [nom, libellé de fonction]
     */
    private function importer(array $agents): PersonnelImport
    {
        $feuille = (new Spreadsheet)->getActiveSheet();

        $feuille->fromArray([
            ['MISE EN PLACE DU PERSONNEL'],
            [],
            ['Noms', 'Fonction'],
            [null, null],
            ...array_map(fn (array $agent) => [$agent[0], $agent[1]], $agents),
        ], null, 'A1');

        $chemin = tempnam(sys_get_temp_dir(), 'pers').'.xlsx';
        (new Xlsx($feuille->getParent()))->save($chemin);

        $import = new PersonnelImport($this->school->id);
        Excel::import($import, new UploadedFile($chemin, 'personnel.xlsx', null, null, true));

        return $import;
    }

    private function fonctionDe(string $nom): ?FonctionReferentiel
    {
        return Personnel::where('nom_complet', $nom)->firstOrFail()->fonctionReference;
    }

    public function test_le_libelle_anglais_retombe_sur_la_fonction_du_referentiel(): void
    {
        $this->importer([['NGWA PETER', 'Teacher'], ['TUME MEDIAN', 'Bursar']]);

        $this->assertSame('Enseignant', $this->fonctionDe('NGWA PETER')?->label_fr);
        $this->assertSame('Économe', $this->fonctionDe('TUME MEDIAN')?->label_fr);

        $this->assertSame(
            2,
            FonctionReferentiel::where('school_id', $this->school->id)->count(),
            'Un doublon a été créé au lieu de rapprocher le libellé anglais.',
        );
    }

    /** Casse, accents et ponctuation ne doivent pas faire manquer le rapprochement. */
    public function test_le_libelle_est_rapproche_a_la_normalisation_pres(): void
    {
        $this->importer([['AMONET DOREEN', 'ENSEIGNANT'], ['USENI VENYITEH', 'econome']]);

        $this->assertSame('Enseignant', $this->fonctionDe('AMONET DOREEN')?->label_fr);
        $this->assertSame('Économe', $this->fonctionDe('USENI VENYITEH')?->label_fr);
        $this->assertSame(2, FonctionReferentiel::where('school_id', $this->school->id)->count());
    }

    /**
     * Une fonction vraiment inconnue reste ajoutée — l'import reprend un
     * existant, il ne le juge pas — mais une seule fois pour tout le classeur.
     */
    public function test_une_fonction_inconnue_n_est_creee_qu_une_fois(): void
    {
        $this->importer([['SONG ERIC', 'Maître-nageur'], ['VIBAN LOVELINE', 'Maître-nageur']]);

        $creees = FonctionReferentiel::where('school_id', $this->school->id)
            ->where('label_fr', 'Maître-nageur')
            ->get();

        $this->assertCount(1, $creees);
        $this->assertSame($creees->first()->id, $this->fonctionDe('SONG ERIC')?->id);
        $this->assertSame($creees->first()->id, $this->fonctionDe('VIBAN LOVELINE')?->id);
    }

    /** Sans colonne renseignée, l'agent reste sans fonction, à doter à la main. */
    public function test_une_fonction_absente_laisse_l_agent_sans_fonction(): void
    {
        $this->importer([['TANTOH MAXILUS', null]]);

        $this->assertNull($this->fonctionDe('TANTOH MAXILUS'));
    }
}
