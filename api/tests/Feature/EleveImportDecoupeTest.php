<?php

namespace Tests\Feature;

use App\Models\Eleve;
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
 * Import du fichier de situation découpé en petits lots — le correctif pour
 * un gros effectif (500+ lignes) dont l'import synchrone d'un seul tenant
 * dépasse le délai d'exécution du serveur en production, hors de portée sans
 * accès devops. Chaque lot rejoue `EleveImport` inchangée sur un fichier bien
 * plus petit, via sa propre requête HTTP.
 */
class EleveImportDecoupeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites College', 'code' => 'EBTC', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function fichier(int $nombreLignes): UploadedFile
    {
        $tableur = new Spreadsheet;
        $feuille = $tableur->getActiveSheet();
        $feuille->fromArray(['IDEleves', 'nom_eleves', 'sexe_eleves', 'etat_eleves'], null, 'A1');

        for ($i = 1; $i <= $nombreLignes; $i++) {
            $feuille->fromArray(["E{$i}", "ELEVE {$i}", $i % 2 === 0 ? 'F' : 'M', 'Actif'], null, 'A'.($i + 1));
        }

        $chemin = tempnam(sys_get_temp_dir(), 'eleves').'.xlsx';
        (new Xlsx($tableur))->save($chemin);

        return new UploadedFile($chemin, 'situation.xlsx', null, null, true);
    }

    private function preparer(UploadedFile $fichier): array
    {
        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/eleves/import/preparer', ['file' => $fichier])
            ->assertOk();

        return $reponse->json('data');
    }

    private function traiterLot(string $token, int $index): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson("/api/v1/eleves/import/traiter/{$token}", ['index' => $index]);
    }

    public function test_un_fichier_de_130_lignes_se_decoupe_en_trois_lots_de_60(): void
    {
        ['token' => $token, 'lots' => $lots] = $this->preparer($this->fichier(130));

        $this->assertSame(3, $lots);

        $totalImporte = 0;
        for ($i = 0; $i < $lots; $i++) {
            $reponse = $this->traiterLot($token, $i)->assertOk();
            $totalImporte += $reponse->json('data.imported');
            $this->assertSame($i === $lots - 1, $reponse->json('data.dernier'));
        }

        $this->assertSame(130, $totalImporte);
        $this->assertSame(130, Eleve::count());
    }

    /** Le lot déjà traité est supprimé : le rejouer échoue proprement plutôt que de dupliquer les élèves. */
    public function test_rejouer_un_lot_deja_traite_echoue_sans_dupliquer(): void
    {
        ['token' => $token] = $this->preparer($this->fichier(10));

        $this->traiterLot($token, 0)->assertOk();
        $this->assertSame(10, Eleve::count());

        $this->traiterLot($token, 0)
            ->assertStatus(422)
            ->assertJsonPath('message', "Ce lot est introuvable — il a peut-être déjà été traité, ou l'import a expiré.");

        $this->assertSame(10, Eleve::count());
    }

    /** Le dossier temporaire ne doit rien laisser traîner une fois le dernier lot passé. */
    public function test_le_dossier_temporaire_est_nettoye_apres_le_dernier_lot(): void
    {
        ['token' => $token, 'lots' => $lots] = $this->preparer($this->fichier(5));

        for ($i = 0; $i < $lots; $i++) {
            $this->traiterLot($token, $i)->assertOk();
        }

        $this->assertDirectoryDoesNotExist(storage_path('app/private/imports-eleves/'.$token));
    }
}
