<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\GrilleFrais;
use App\Models\InventaireArticle;
use App\Models\Niveau;
use App\Models\School;
use App\Models\SousSysteme;
use App\Models\User;
use App\Support\CataloguePermissions;
use App\Support\ImportExport\Specs\GrilleFraisSpec;
use App\Support\ImportExport\Specs\InventaireArticleSpec;
use App\Support\ImportExport\Specs\NiveauSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Le moteur générique (ImportGenerique/ExportGenerique/ModeleGenerique piloté
 * par une SpecificationModele) — vérifié sur trois profils représentatifs du
 * premier lot : un modèle avec une FK résolue par libellé (Niveaux), un
 * modèle entièrement scalaire (Inventaire), un modèle à deux FK dont une
 * implicite — l'année active (Grille de frais).
 */
class ImportExportGeneriqueTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $this->user = User::factory()->create(['school_id' => $this->school->id]);
        $this->user->assignRole('super_admin');
    }

    private function fichier(array $entetes, array $lignes, string $prefixe = 'gen'): UploadedFile
    {
        $feuille = (new Spreadsheet)->getActiveSheet();
        $feuille->fromArray([$entetes, ...$lignes], null, 'A1');

        $chemin = tempnam(sys_get_temp_dir(), $prefixe).'.xlsx';
        (new Xlsx($feuille->getParent()))->save($chemin);

        return new UploadedFile($chemin, "{$prefixe}.xlsx", null, null, true);
    }

    // --------------------------------------------------------------- Niveaux

    public function test_import_niveau_resout_le_sous_systeme_par_libelle(): void
    {
        SousSysteme::create(['school_id' => $this->school->id, 'code' => 'ANG', 'nom' => 'Anglophone']);

        $import = new \App\Imports\ImportGenerique(new NiveauSpec(), $this->school->id);
        Excel::import($import, $this->fichier(
            ['Code', 'Nom (FR)', 'Nom (EN)', 'Sous-système'],
            [['6EME', 'Sixième', 'Form 1', 'Anglophone']],
            'niveau',
        ));

        $this->assertSame(1, $import->importedCount);
        $this->assertCount(0, $import->failures());

        $niveau = Niveau::where('school_id', $this->school->id)->where('code', '6EME')->firstOrFail();
        $this->assertSame('Sixième', $niveau->name_fr);
        $this->assertSame(SousSysteme::where('code', 'ANG')->value('id'), $niveau->sous_system_id);
    }

    public function test_reimport_niveau_met_a_jour_plutot_que_dupliquer(): void
    {
        $import1 = new \App\Imports\ImportGenerique(new NiveauSpec(), $this->school->id);
        Excel::import($import1, $this->fichier(['Code', 'Nom (FR)', 'Nom (EN)'], [['6EME', 'Sixième', 'Form 1']], 'niveau1'));

        $import2 = new \App\Imports\ImportGenerique(new NiveauSpec(), $this->school->id);
        Excel::import($import2, $this->fichier(['Code', 'Nom (FR)', 'Nom (EN)'], [['6EME', 'Sixième (corrigé)', 'Form 1']], 'niveau2'));

        $this->assertSame(1, $import2->updatedCount);
        $this->assertSame(1, Niveau::where('school_id', $this->school->id)->count());
        $this->assertSame('Sixième (corrigé)', Niveau::where('school_id', $this->school->id)->first()->name_fr);
    }

    public function test_route_modele_niveaux_renvoie_un_classeur_avec_les_bons_en_tetes(): void
    {
        $reponse = $this->actingAs($this->user, 'sanctum')->get('/api/v1/niveaux/modele');

        $reponse->assertOk();
        $this->assertSame('attachment; filename=modele-niveau.xlsx', $reponse->headers->get('content-disposition'));
    }

    public function test_route_export_niveaux_fonctionne_de_bout_en_bout(): void
    {
        Niveau::create(['school_id' => $this->school->id, 'code' => '6EME', 'name_fr' => 'Sixième', 'name_en' => 'Form 1']);

        $reponse = $this->actingAs($this->user, 'sanctum')->get('/api/v1/niveaux/export');

        $reponse->assertOk();
        $this->assertSame('attachment; filename=niveau.xlsx', $reponse->headers->get('content-disposition'));
    }

    // ------------------------------------------------------------ Inventaire

    public function test_import_export_inventaire_aller_retour(): void
    {
        $import = new \App\Imports\ImportGenerique(new InventaireArticleSpec(), $this->school->id);
        Excel::import($import, $this->fichier(
            ['Article', 'Catégorie', 'Quantité', 'État', 'Valeur unitaire'],
            [['Craies', 'Fournitures', 200, 'bon', 50]],
            'inventaire',
        ));

        $this->assertSame(1, $import->importedCount);
        $article = InventaireArticle::where('school_id', $this->school->id)->where('nom', 'Craies')->firstOrFail();
        $this->assertSame(200, $article->quantite);
        $this->assertSame('bon', $article->etat);

        $reponse = $this->actingAs($this->user, 'sanctum')->get('/api/v1/inventaire/export');
        $reponse->assertOk();
    }

    // ------------------------------------------------------------ Grille de frais

    public function test_import_grille_frais_resout_la_classe_et_lannee_active(): void
    {
        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6EME A', 'niveau_classe' => '6eme']);

        $import = new \App\Imports\ImportGenerique(new GrilleFraisSpec(), $this->school->id);
        Excel::import($import, $this->fichier(['Classe', 'Montant'], [['6EME A', 150000]], 'grille'));

        $this->assertSame(1, $import->importedCount);

        $grille = GrilleFrais::where('school_id', $this->school->id)->firstOrFail();
        $this->assertSame($classe->id, $grille->classe_id);
        $this->assertSame($annee->id, $grille->annee_scolaire_id);
        $this->assertSame(150000, $grille->montant);
    }
}
