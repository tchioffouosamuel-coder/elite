<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DetteAnterieure;
use App\Models\DossierScolarite;
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
 * Le fichier de situation exporté en fin d'année porte, par élève, ce qui
 * restait dû : frais_scolarite - montant_scolarite (déjà réglé, malgré son
 * nom) - remise_scol. L'import doit reprendre ce solde en dette antérieure,
 * pour qu'il rejoigne le report_dette du dossier de la nouvelle année dès
 * qu'il s'ouvre — sans jamais dupliquer un report déjà pris en compte.
 */
class EleveImportDetteTest extends TestCase
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

    /** @param list<array<string, mixed>> $lignes */
    private function fichier(array $lignes): UploadedFile
    {
        $entetes = [
            'IDEleves', 'nom_eleves', 'sexe_eleves', 'etat_eleves',
            'frais_scolarite', 'montant_scolarite', 'remise_scol', 'annee_scol',
        ];

        $tableur = new Spreadsheet;
        $feuille = $tableur->getActiveSheet();
        $feuille->fromArray($entetes, null, 'A1');

        foreach (array_values($lignes) as $index => $ligne) {
            $feuille->fromArray([
                $ligne['matricule'], $ligne['nom'], $ligne['sexe'] ?? 'F', 'Actif',
                $ligne['frais_scolarite'] ?? null, $ligne['montant_scolarite'] ?? null,
                $ligne['remise_scol'] ?? null, $ligne['annee_scol'] ?? '2025/2026',
            ], null, 'A'.($index + 2));
        }

        $chemin = tempnam(sys_get_temp_dir(), 'eleves').'.xlsx';
        (new Xlsx($tableur))->save($chemin);

        return new UploadedFile($chemin, 'situation.xlsx', null, null, true);
    }

    private function importer(UploadedFile $fichier): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->post('/api/v1/eleves/import', ['file' => $fichier]);
    }

    public function test_le_solde_du_est_repris_en_dette_anterieure(): void
    {
        $fichier = $this->fichier([[
            'matricule' => '23MAT5', 'nom' => 'KENGNE NEIL',
            'frais_scolarite' => 90000, 'montant_scolarite' => 60000, 'remise_scol' => 0,
        ]]);

        $this->importer($fichier)
            ->assertOk()
            ->assertJsonPath('data.dettes', 1)
            ->assertJsonPath('data.dettes_montant', 30000);

        $eleve = Eleve::where('matricule', '23MAT5')->firstOrFail();
        $dette = DetteAnterieure::where('eleve_id', $eleve->id)->firstOrFail();

        $this->assertSame(30000, $dette->montant);
        $this->assertNull($dette->imputee_dossier_id);
        $this->assertStringContainsString('2025/2026', $dette->motif);
    }

    /** frais - payé - remise : la remise réduit ce qui est repris en dette. */
    public function test_la_remise_reduit_la_dette_reprise(): void
    {
        $fichier = $this->fichier([[
            'matricule' => '23MAT9', 'nom' => 'NGON SARAH',
            'frais_scolarite' => 90000, 'montant_scolarite' => 50000, 'remise_scol' => 20000,
        ]]);

        $this->importer($fichier)->assertOk();

        $eleve = Eleve::where('matricule', '23MAT9')->firstOrFail();

        // 90000 - 50000 - 20000 = 20000.
        $this->assertSame(20000, DetteAnterieure::where('eleve_id', $eleve->id)->sole()->montant);
    }

    /** Une ligne soldée (frais = payé) ne crée aucune dette. */
    public function test_une_ligne_soldee_ne_cree_pas_de_dette(): void
    {
        $fichier = $this->fichier([[
            'matricule' => '23MAT7', 'nom' => 'TSOUNGUI MAEL',
            'frais_scolarite' => 90000, 'montant_scolarite' => 90000, 'remise_scol' => 0,
        ]]);

        $this->importer($fichier)->assertOk()->assertJsonPath('data.dettes', 0);

        $this->assertSame(0, DetteAnterieure::count());
    }

    /** Un trop-perçu (payé > frais) ne crée pas de dette négative. */
    public function test_un_trop_percu_ne_cree_pas_de_dette(): void
    {
        $fichier = $this->fichier([[
            'matricule' => '24MAT2', 'nom' => 'MBIMINYENGONG QUEEN',
            'frais_scolarite' => 90000, 'montant_scolarite' => 95000, 'remise_scol' => 0,
        ]]);

        $this->importer($fichier)->assertOk()->assertJsonPath('data.dettes', 0);

        $this->assertSame(0, DetteAnterieure::count());
    }

    /** Réimporter le même fichier de situation ne double pas le report. */
    public function test_reimporter_le_meme_fichier_ne_duplique_pas_la_dette(): void
    {
        $fichier = fn () => $this->fichier([[
            'matricule' => '23MAT13', 'nom' => 'ANUMBEB ERICA',
            'frais_scolarite' => 84500, 'montant_scolarite' => 10000, 'remise_scol' => 0,
        ]]);

        $this->importer($fichier())->assertOk()->assertJsonPath('data.dettes', 1);

        $reponse = $this->importer($fichier())
            ->assertOk()
            ->assertJsonPath('data.dettes', 0)
            ->assertJsonPath('data.dettes_ignorees', 1);

        $eleve = Eleve::where('matricule', '23MAT13')->firstOrFail();

        $this->assertSame(1, DetteAnterieure::where('eleve_id', $eleve->id)->count());
        $this->assertSame(74500, DetteAnterieure::where('eleve_id', $eleve->id)->sole()->montant);
        unset($reponse);
    }

    /**
     * Si un dossier de l'année active est déjà ouvert pour l'élève, la dette
     * s'y impute aussitôt — c'est le comportement déjà établi de
     * ScolariteService::enregistrerDetteAnterieure, que l'import réutilise.
     */
    public function test_la_dette_s_impute_a_un_dossier_deja_ouvert(): void
    {
        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);
        $classe = Classe::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $annee->id, 'nom' => 'ACCOUNTING 1',
        ]);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'matricule' => '23MAT5', 'nom_complet' => 'KENGNE NEIL', 'sexe' => 'F', 'statut' => 'actif',
        ]);
        $dossier = DossierScolarite::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $annee->id, 'eleve_id' => $eleve->id,
            'montant_scolarite' => 90000, 'remise' => 0, 'report_dette' => 0,
        ]);

        $fichier = $this->fichier([[
            'matricule' => '23MAT5', 'nom' => 'KENGNE NEIL',
            'frais_scolarite' => 90000, 'montant_scolarite' => 60000, 'remise_scol' => 0,
        ]]);

        $this->importer($fichier)->assertOk();

        $this->assertSame(30000, $dossier->fresh()->report_dette);

        $dette = DetteAnterieure::where('eleve_id', $eleve->id)->sole();
        $this->assertSame($dossier->id, $dette->imputee_dossier_id);
    }
}
