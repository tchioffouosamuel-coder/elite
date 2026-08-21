<?php

namespace Tests\Feature;

use App\Imports\PersonnelImport;
use App\Models\Personnel;
use App\Models\School;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Vérifie que le classeur remis pour le secondaire tombe bien dans le flux
 * d'import — en-têtes en ligne 3, colonnes A à T, libellés reconnus.
 *
 * Le test porte sur le fichier réel plutôt que sur un classeur fabriqué ici :
 * c'est précisément l'accord entre CE fichier et l'importeur qu'on veut tenir
 * dans le temps. Si le fichier n'est plus là, le test se met de côté au lieu
 * de rougir : il documente un format, il ne garde pas une donnée.
 */
class PersonnelImportFichierSecondaireTest extends TestCase
{
    use RefreshDatabase;

    private const FICHIER = __DIR__.'/../../../personnel_secondaire_import.xlsx';

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(self::FICHIER)) {
            $this->markTestSkipped('personnel_secondaire_import.xlsx absent du dépôt.');
        }

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true,
        ]);
    }

    private function importer(): PersonnelImport
    {
        $import = new PersonnelImport($this->school->id);
        Excel::import($import, self::FICHIER);

        return $import;
    }

    public function test_le_fichier_importe_les_32_agents_sans_echec(): void
    {
        $import = $this->importer();

        $this->assertSame(0, count($import->failures()), 'Lignes en échec : '.json_encode($import->failures()));
        $this->assertSame(32, $import->importedCount);
        $this->assertSame(32, Personnel::where('school_id', $this->school->id)->count());
    }

    public function test_les_champs_du_dossier_sont_correctement_rattaches(): void
    {
        $this->importer();

        $agent = Personnel::where('school_id', $this->school->id)
            ->where('nom_complet', 'USENI VENYITEH')
            ->firstOrFail();

        $this->assertSame('003/E-Tech/23', $agent->matricule);
        $this->assertSame('Mr', $agent->civilite);
        // Le sexe n'est pas une colonne : il se déduit de la civilité.
        $this->assertSame('M', $agent->sexe);
        $this->assertSame('330-1039070-6', $agent->numero_cnps);
        $this->assertSame('1995-02-23', $agent->date_naissance?->toDateString());
        $this->assertSame('2023-09-01', $agent->date_embauche?->toDateString());
        $this->assertSame('2055-02-23', $agent->date_retraite?->toDateString());
        $this->assertSame('NGOKETUNJIA', $agent->departement_origine);
        $this->assertSame('NGAIKADA', $agent->residence);
        $this->assertSame('673013265', $agent->telephone);
        $this->assertSame('celibataire', $agent->situation_matrimoniale);
        $this->assertSame(1, $agent->nombre_enfants);
        $this->assertSame('Principal', $agent->affectation);
    }

    public function test_le_second_numero_et_la_civilite_feminine_sont_repris(): void
    {
        $this->importer();

        $agent = Personnel::where('school_id', $this->school->id)
            ->where('nom_complet', 'AMONET DOREEN AZICHA')
            ->firstOrFail();

        $this->assertSame('F', $agent->sexe);
        $this->assertSame('670330336', $agent->telephone);
        $this->assertSame('654458210', $agent->telephone_2);
        // « Maried », faute de frappe du fichier source, doit tomber sur « marié ».
        $this->assertSame('marie', $agent->situation_matrimoniale);
    }

    /**
     * Le point qui justifiait de retoucher le fichier : sa colonne « Date end »
     * portait l'âge de l'agent, pas une fin de contrat. Reprise telle quelle,
     * elle aurait sorti tout l'effectif des effectifs actifs.
     */
    public function test_aucun_agent_n_est_marque_ex_employe(): void
    {
        $this->importer();

        $sortis = Personnel::where('school_id', $this->school->id)->where('statut', 'ex_employe')->get();

        $this->assertCount(0, $sortis, 'Agents sortis à tort : '.$sortis->pluck('nom_complet')->join(', '));
        $this->assertNull(Personnel::where('school_id', $this->school->id)->whereNotNull('date_fin')->first());
    }

    /**
     * Le fichier n'a pas de colonne « fonction » : la deviner depuis le poste
     * occupé peuplerait le référentiel de libellés qui portent des privilèges.
     * Les agents arrivent donc sans fonction, à doter depuis l'interface.
     */
    public function test_les_agents_arrivent_sans_fonction_a_doter(): void
    {
        $this->importer();

        $this->assertSame(
            32,
            Personnel::where('school_id', $this->school->id)->whereNull('fonction_id')->count(),
        );
    }

    /** Réimporter le même fichier met à jour, il ne duplique pas. */
    public function test_le_reimport_ne_cree_pas_de_doublon(): void
    {
        $this->importer();
        $second = $this->importer();

        $this->assertSame(0, $second->importedCount);
        $this->assertSame(32, $second->updatedCount);
        $this->assertSame(32, Personnel::where('school_id', $this->school->id)->count());
    }
}
