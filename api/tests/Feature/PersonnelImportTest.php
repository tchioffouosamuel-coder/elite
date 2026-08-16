<?php

namespace Tests\Feature;

use App\Imports\PersonnelImport;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Personnel;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Reproduit la forme du tableau de mise en place du personnel : titre en ligne
 * 1, totaux en ligne 2, en-têtes en ligne 3, taux de cotisation en ligne 4,
 * agents à partir de la ligne 5 — et les colonnes d'identité répétées à droite
 * sous forme de formules par les blocs de paie.
 */
class PersonnelImportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'maternelle', 'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2025-2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-07-31',
            'is_active' => true,
        ]);

        Classe::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $annee->id, 'nom' => 'NURSERY 1-A',
        ]);
    }

    private function fichier(): UploadedFile
    {
        $feuille = (new Spreadsheet)->getActiveSheet();

        $feuille->fromArray([
            ['MISE EN PLACE DU PERSONNEL — GLOBAL STAFF STATUS'],
            [],
            [
                'N°', 'Civilité', "Teachers name /\n NOMS DES ENSEIGNANTS", 'Duty poste 2021', "Affectations \nDuty post",
                'Numéro unique', 'Matricules', 'N°CNPS', 'Births', 'Date Start', 'Date end', 'Date retraite',
                'Division of Origine', 'Residence', 'ORANGE', 'MTN', 'Married?', 'NB children <21yrs',
                "Diplôme\nProf", "Diplôme\nAcademic",
                // Début du premier bloc de paie : mêmes en-têtes, valeurs calculées.
                'N°', 'Civilité', "Teachers name /\n NOMS DES ENSEIGNANTS",
            ],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            [
                1, 'Mrs', 'AGBORNDE CATHERINE BESONG', 'Nursery 1A', 'Nursery 1-A',
                'P028513407817B', '008/Elites/17 ', '330-1027740-8', '1985-02-04', '2016-09-01', null, '2045-09-04',
                'MANYU', 'MONOU2', 677287504, 690082984, 'Mariée', 5, 'CAPIEM', 'O-LEVEL',
                '=A5', '=B5', '=C5',
            ],
            [
                2, 'Mr', 'NDJOMO PAUL', null, 'Bus driver',
                ',', null, '330-1042196-4', '1990-11-05', '2022-10-10', '2024-06-30', null,
                'BUI', 'ENIA', null, 676846229, 'Celibataire', 0, null, 'A-LEVEL',
                '=A6', '=B6', '=C6',
            ],
            // Ligne de totaux : pas de nom, donc pas un agent.
            [null, null, null, null, '=SUM(E5:E6)'],
        ], null, 'A1');

        $chemin = tempnam(sys_get_temp_dir(), 'pers').'.xlsx';
        (new Xlsx($feuille->getParent()))->save($chemin);

        return new UploadedFile($chemin, 'personnel.xlsx', null, null, true);
    }

    private function importer(): PersonnelImport
    {
        $import = new PersonnelImport($this->school->id);
        Excel::import($import, $this->fichier());

        return $import;
    }

    public function test_seules_les_lignes_portant_un_nom_sont_importees(): void
    {
        $import = $this->importer();

        $this->assertSame(2, $import->importedCount);
        $this->assertCount(0, $import->failures());
        $this->assertSame(2, Personnel::where('school_id', $this->school->id)->count());
    }

    public function test_le_dossier_administratif_est_repris(): void
    {
        $this->importer();

        $agent = Personnel::where('matricule', '008/Elites/17')->firstOrFail();

        $this->assertSame('AGBORNDE CATHERINE BESONG', $agent->nom_complet);
        $this->assertSame('Mrs', $agent->civilite);
        $this->assertSame('F', $agent->sexe);
        $this->assertSame('1985-02-04', $agent->date_naissance->toDateString());
        $this->assertSame('P028513407817B', $agent->numero_cni);
        $this->assertSame('330-1027740-8', $agent->numero_cnps);
        $this->assertSame('MANYU', $agent->departement_origine);
        $this->assertSame('MONOU2', $agent->residence);
        $this->assertSame('677287504', $agent->telephone);
        $this->assertSame('690082984', $agent->telephone_2);
        $this->assertSame('marie', $agent->situation_matrimoniale);
        $this->assertSame(5, $agent->nombre_enfants);
        $this->assertSame('CAPIEM', $agent->diplome_professionnel);
        $this->assertSame('O-LEVEL', $agent->diplome_academique);
        $this->assertSame('2016-09-01', $agent->date_embauche->toDateString());
        $this->assertSame('actif', $agent->statut);
    }

    /** Les blocs de paie répètent les en-têtes d'identité avec des formules. */
    public function test_les_colonnes_de_paie_ne_polluent_pas_l_identite(): void
    {
        $this->importer();

        $this->assertSame(
            0,
            Personnel::where('nom_complet', 'like', '=%')->count(),
            "Une formule d'un bloc de paie a écrasé le nom de l'agent.",
        );
    }

    public function test_une_date_de_fin_marque_l_agent_comme_sorti(): void
    {
        $this->importer();

        $agent = Personnel::where('nom_complet', 'NDJOMO PAUL')->firstOrFail();

        $this->assertSame('ex_employe', $agent->statut);
        $this->assertSame('2024-06-30', $agent->date_fin->toDateString());
        // « , » ne vaut pas numéro de CNI.
        $this->assertNull($agent->numero_cni);
    }

    public function test_l_affectation_rattache_a_la_classe_quand_elle_existe(): void
    {
        $import = $this->importer();

        $agent = Personnel::where('nom_complet', 'AGBORNDE CATHERINE BESONG')->firstOrFail();

        $this->assertSame('Nursery 1-A', $agent->affectation);
        $this->assertSame($agent->id, Classe::where('nom', 'NURSERY 1-A')->value('titulaire_id'));

        // « Bus driver » n'est pas une classe : le libellé est gardé, signalé,
        // et ne bloque pas la création de l'agent.
        $this->assertSame('Bus driver', Personnel::where('nom_complet', 'NDJOMO PAUL')->value('affectation'));
        $this->assertSame(['Bus driver' => 1], $import->affectationsNonRattachees);
    }

    public function test_le_reimport_met_a_jour_au_lieu_de_dupliquer(): void
    {
        $this->importer();
        $rejeu = $this->importer();

        $this->assertSame(0, $rejeu->importedCount);
        $this->assertSame(2, $rejeu->updatedCount);
        $this->assertSame(2, Personnel::where('school_id', $this->school->id)->count());
    }
}
