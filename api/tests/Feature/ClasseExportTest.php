<?php

namespace Tests\Feature;

use App\Exports\ClasseExport;
use App\Imports\ClasseImport;
use App\Models\Classe;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * L'export des classes doit se relire par l'import — mêmes en-têtes,
 * mêmes colonnes — pour qu'un établissement puisse exporter, corriger au
 * tableur, puis réimporter tel quel.
 */
class ClasseExportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
    }

    public function test_l_export_se_relit_par_l_import(): void
    {
        Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2 A', 'sigle' => 'CM2A', 'capacite' => 45]);
        Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2 B', 'capacite' => null]);

        $lignes = (new ClasseExport($this->school->id))->collection()->map(fn (Classe $c) => (new ClasseExport($this->school->id))->map($c));
        $entetes = ClasseImport::enTetes();

        $this->assertCount(2, $lignes);
        $this->assertSame(['Nom', 'Sigle', 'Capacite'], $entetes);

        $slugs = array_map(fn (string $entete) => Str::slug($entete, '_'), $entetes);

        Classe::query()->delete();

        $import = new ClasseImport($this->school->id);
        foreach ($lignes as $ligne) {
            $import->model(array_combine($slugs, $ligne))?->save();
        }

        $this->assertSame(2, $import->importedCount);
        $this->assertSame(2, Classe::count());

        $relue = Classe::where('nom', 'CM2 A')->firstOrFail();
        $this->assertSame('CM2A', $relue->sigle);
        $this->assertSame(45, $relue->capacite);
    }

    public function test_lendpoint_export_repond_un_classeur(): void
    {
        Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2 A']);

        $admin = \App\Models\User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $reponse = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->get('/api/v1/classes/export');

        $reponse->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $reponse->headers->get('content-type'),
        );
    }
}
