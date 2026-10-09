<?php

namespace Tests\Feature;

use App\Imports\VisiteInfirmerieImport;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use App\Models\VisiteInfirmerie;
use App\Services\InfirmerieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisiteInfirmerieImportTest extends TestCase
{
    use RefreshDatabase;

    private function fichier(array $lignes): UploadedFile
    {
        $classeur = new Spreadsheet;
        $classeur->getActiveSheet()->fromArray([VisiteInfirmerieImport::enTetes(), ...$lignes], null, 'A1');
        $chemin = tempnam(sys_get_temp_dir(), 'inf').'.xlsx';
        (new Xlsx($classeur))->save($chemin);

        return new UploadedFile($chemin, 'visites.xlsx', null, null, true);
    }

    public function test_les_erreurs_de_validation_et_les_eleves_introuvables_sont_signales(): void
    {
        $school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'primaire', 'is_active' => true]);
        $eleve = Eleve::create(['school_id' => $school->id, 'nom_complet' => 'Alice Ngono', 'sexe' => 'F', 'statut' => 'actif']);
        $user = User::create(['school_id' => $school->id, 'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password', 'is_active' => true]);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole('super_admin');

        $this->actingAs($user, 'sanctum')->withHeader('X-School-Id', $school->id)
            ->postJson('/api/v1/infirmerie/visites/import', ['file' => $this->fichier([
                [null, $eleve->matricule, $eleve->nom_complet, '2026-10-09', 'Repos', null, 'Surveillance'],
                [null, null, null, null, null, null],
                [null, $eleve->matricule, $eleve->nom_complet, null, null, null, 'Surveillance'],
                [null, 'INCONNU', 'Élève inconnu', '2026-10-09', 'Repos', null, 'Surveillance'],
            ])])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.failed', 2)
            ->assertJsonCount(2, 'data.errors')
            ->assertJsonPath('data.errors.0.row', 4)
            ->assertJsonPath('data.erreurs_metier.0.ligne', 5)
            ->assertJsonPath('data.erreurs_metier.0.nom', 'Élève inconnu');

        $this->assertSame(1, VisiteInfirmerie::count());
        $visite = VisiteInfirmerie::firstOrFail();
        $import = new VisiteInfirmerieImport($school->id, app(InfirmerieService::class));
        Excel::import($import, $this->fichier([
            [$visite->id, $eleve->matricule, $eleve->nom_complet, '2026-10-09', 'Repos prolongé', null, 'Surveillance'],
        ]));

        $this->assertSame(1, $import->updatedCount);
        $this->assertSame(0, $import->importedCount);
        $this->assertSame('Repos prolongé', $visite->fresh()->raison);
    }
}
