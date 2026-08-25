<?php

namespace Tests\Feature;

use App\Models\Matiere;
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
 * Import du catalogue des matières par un super admin en mode agrégé.
 *
 * Le bug de production reproduit ici : `MatiereController::import()` appelait
 * `Tenant::schoolId()` (singulier) sans passer par `resolveWriteSchoolId()`,
 * si bien qu'en mode agrégé (aucune école active, aucun `X-School-Id`)
 * l'import écrivait sous l'école propre du compte super admin plutôt que sous
 * celle affichée par la liste — « import réussi » mais lignes introuvables.
 */
class MatiereImportAgregeTest extends TestCase
{
    use RefreshDatabase;

    private School $ecoleCompte;
    private School $autreEcole;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // L'école « propre » du compte super admin — celle que l'ancien code
        // utilisait aveuglément — est délibérément différente de celle que
        // l'admin veut réellement cibler.
        $this->ecoleCompte = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $this->autreEcole = School::create(['name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'secondaire', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->ecoleCompte->id, 'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');
    }

    private function fichier(): UploadedFile
    {
        $tableur = new Spreadsheet;
        $feuille = $tableur->getActiveSheet();
        $feuille->fromArray(['nom', 'nom_en', 'abreviation'], null, 'A1');
        $feuille->fromArray(['Mathématiques', 'Mathematics', 'MATHS'], null, 'A2');

        $chemin = tempnam(sys_get_temp_dir(), 'matieres').'.xlsx';
        (new Xlsx($tableur))->save($chemin);

        return new UploadedFile($chemin, 'matieres.xlsx', null, null, true);
    }

    /**
     * Le cycle `maternelle` est distinct de `primaire` uniquement pour que
     * l'établissement visé soit déclaré explicitement — le traitement est
     * identique, et identique à celui du secondaire : seule une matière est
     * créée, sans compétence associée (cf. MatiereImport).
     */
    public function test_le_cycle_maternelle_importe_une_matiere_sous_la_bonne_ecole(): void
    {
        $maternelle = School::create(['name' => 'Elites Maternelle', 'code' => 'EM', 'type' => 'maternelle', 'is_active' => true]);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->post('/api/v1/matieres/import', [
                'file' => $this->fichier(),
                'cycle' => 'maternelle',
                'school_id' => $maternelle->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $matiere = Matiere::sole();
        $this->assertSame($maternelle->id, $matiere->school_id);
        $this->assertNull($matiere->competence_id);
    }

    /** Sans école explicite ni X-School-Id, deviner serait justement le bug : l'API doit refuser plutôt qu'écrire au hasard. */
    public function test_le_mode_agrege_sans_ecole_precisee_est_refuse(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->post('/api/v1/matieres/import', [
                'file' => $this->fichier(),
                'cycle' => 'secondaire',
            ])
            ->assertStatus(422);

        $this->assertSame(0, Matiere::count());
    }

    /** Un `school_id` explicite dans la requête cible la bonne école, même en mode agrégé. */
    public function test_un_school_id_explicite_cible_la_bonne_ecole_en_mode_agrege(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->post('/api/v1/matieres/import', [
                'file' => $this->fichier(),
                'cycle' => 'secondaire',
                'school_id' => $this->autreEcole->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $matiere = Matiere::sole();
        $this->assertSame($this->autreEcole->id, $matiere->school_id);
        // La régression exacte du bug : la matière n'atterrit pas sous
        // l'école propre du compte super admin.
        $this->assertNotSame($this->ecoleCompte->id, $matiere->school_id);
    }

    /** Un compte fixé sur une école (X-School-Id) garde l'ancien comportement, sans avoir à préciser `school_id`. */
    public function test_avec_x_school_id_l_import_cible_l_ecole_active(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->withHeader('X-School-Id', $this->autreEcole->id)
            ->post('/api/v1/matieres/import', [
                'file' => $this->fichier(),
                'cycle' => 'secondaire',
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $this->assertSame($this->autreEcole->id, Matiere::sole()->school_id);
    }
}
