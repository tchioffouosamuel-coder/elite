<?php

namespace Tests\Feature;

use App\Models\Classe;
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
 * Gestion en masse du matricule national (secondaire uniquement) : liste,
 * recherche, modification/suppression, export, modèle à remplir, import.
 */
class MatriculeNationalTest extends TestCase
{
    use RefreshDatabase;

    private School $secondaire;
    private School $primaire;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->secondaire = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $this->primaire = School::create(['name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->secondaire->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function eleve(School $school, string $matricule, string $nom, ?string $matriculeNational = null): Eleve
    {
        return Eleve::create([
            'school_id' => $school->id, 'matricule' => $matricule, 'nom_complet' => $nom,
            'sexe' => 'M', 'date_naissance' => '2010-01-01', 'statut' => 'actif',
            'matricule_national' => $matriculeNational,
        ]);
    }

    public function test_liste_uniquement_les_eleves_du_secondaire(): void
    {
        $this->eleve($this->secondaire, 'SEC1', 'Eleve Secondaire');
        $this->eleve($this->primaire, 'PRIM1', 'Eleve Primaire');

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->secondaire->id)
            ->getJson('/api/v1/matricules-nationaux')
            ->assertOk();

        $noms = collect($reponse->json('data'))->pluck('nom_complet');
        $this->assertTrue($noms->contains('Eleve Secondaire'));
        $this->assertFalse($noms->contains('Eleve Primaire'));
    }

    public function test_la_recherche_filtre_par_nom_matricule_ou_matricule_national(): void
    {
        $this->eleve($this->secondaire, 'SEC1', 'Alpha Bravo', 'MN-100');
        $this->eleve($this->secondaire, 'SEC2', 'Charlie Delta', 'MN-200');

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->secondaire->id)
            ->getJson('/api/v1/matricules-nationaux?search=MN-200')
            ->assertOk();

        $this->assertCount(1, $reponse->json('data'));
        $this->assertSame('Charlie Delta', $reponse->json('data.0.nom_complet'));
    }

    public function test_met_a_jour_le_matricule_national(): void
    {
        $eleve = $this->eleve($this->secondaire, 'SEC1', 'Alpha Bravo');

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->secondaire->id)
            ->putJson("/api/v1/matricules-nationaux/{$eleve->id}", ['matricule_national' => 'MN-999'])
            ->assertOk();

        $this->assertSame('MN-999', $reponse->json('data.matricule_national'));
        $this->assertSame('MN-999', $eleve->fresh()->matricule_national);
    }

    /** « Supprimer un matricule » : la même route, avec une valeur vide. */
    public function test_efface_le_matricule_national_avec_une_valeur_vide(): void
    {
        $eleve = $this->eleve($this->secondaire, 'SEC1', 'Alpha Bravo', 'MN-999');

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->secondaire->id)
            ->putJson("/api/v1/matricules-nationaux/{$eleve->id}", ['matricule_national' => ''])
            ->assertOk();

        $this->assertNull($eleve->fresh()->matricule_national);
    }

    public function test_refuse_un_doublon_de_matricule_national(): void
    {
        $this->eleve($this->secondaire, 'SEC1', 'Alpha Bravo', 'MN-999');
        $cible = $this->eleve($this->secondaire, 'SEC2', 'Charlie Delta');

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->secondaire->id)
            ->putJson("/api/v1/matricules-nationaux/{$cible->id}", ['matricule_national' => 'MN-999'])
            ->assertStatus(422);
    }

    public function test_refuse_le_matricule_national_pour_un_eleve_du_primaire(): void
    {
        $eleve = $this->eleve($this->primaire, 'PRIM1', 'Eleve Primaire');

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->primaire->id)
            ->putJson("/api/v1/matricules-nationaux/{$eleve->id}", ['matricule_national' => 'MN-1'])
            ->assertStatus(422);
    }

    public function test_export_et_modele_renvoient_un_fichier(): void
    {
        $this->eleve($this->secondaire, 'SEC1', 'Alpha Bravo');

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->secondaire->id)
            ->get('/api/v1/matricules-nationaux/export')
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->secondaire->id)
            ->get('/api/v1/matricules-nationaux/modele')
            ->assertOk();
    }

    private function fichierImport(array $lignes): UploadedFile
    {
        $tableur = new Spreadsheet;
        $feuille = $tableur->getActiveSheet();
        $feuille->fromArray(['IDEleves', 'Nom complet', 'Classe', 'École', 'Matricule national'], null, 'A1');

        foreach ($lignes as $i => $ligne) {
            $feuille->fromArray($ligne, null, 'A'.($i + 2));
        }

        $chemin = tempnam(sys_get_temp_dir(), 'matnat').'.xlsx';
        (new Xlsx($tableur))->save($chemin);

        return new UploadedFile($chemin, 'matricules.xlsx', null, null, true);
    }

    public function test_import_met_a_jour_les_lignes_rapprochees_par_matricule_interne(): void
    {
        $eleve = $this->eleve($this->secondaire, 'SEC1', 'Alpha Bravo');

        $fichier = $this->fichierImport([
            ['SEC1', 'Alpha Bravo', null, null, 'MN-100'],
        ]);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->secondaire->id)
            ->postJson('/api/v1/matricules-nationaux/import', ['file' => $fichier])
            ->assertOk();

        $this->assertSame(1, $reponse->json('data.imported'));
        $this->assertSame('MN-100', $eleve->fresh()->matricule_national);
    }

    public function test_import_signale_un_matricule_interne_introuvable(): void
    {
        $fichier = $this->fichierImport([
            ['INCONNU', 'Fantome', null, null, 'MN-1'],
        ]);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->secondaire->id)
            ->postJson('/api/v1/matricules-nationaux/import', ['file' => $fichier])
            ->assertOk();

        $this->assertSame(0, $reponse->json('data.imported'));
        $this->assertCount(1, $reponse->json('data.erreurs'));
    }

    /** Une ligne dont la colonne « Matricule national » n'est pas remplie n'est ni importée ni signalée en erreur. */
    public function test_import_ignore_silencieusement_les_lignes_sans_matricule_national(): void
    {
        $this->eleve($this->secondaire, 'SEC1', 'Alpha Bravo');

        $fichier = $this->fichierImport([
            ['SEC1', 'Alpha Bravo', null, null, null],
        ]);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->secondaire->id)
            ->postJson('/api/v1/matricules-nationaux/import', ['file' => $fichier])
            ->assertOk();

        $this->assertSame(0, $reponse->json('data.imported'));
        $this->assertCount(0, $reponse->json('data.erreurs'));
    }
}
