<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
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
 * Import du fichier de situation en mode agrégé (super admin sans
 * X-School-Id) : le fichier couvre les trois écoles du complexe en une seule
 * feuille, `categorie_ecole` disant à laquelle appartient chaque ligne.
 */
class EleveImportToutesEcolesTest extends TestCase
{
    use RefreshDatabase;

    private School $maternelle;

    private School $primaire;

    private School $college;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->maternelle = School::create(['name' => 'Nursery', 'code' => 'EBNS', 'type' => 'maternelle', 'is_active' => true]);
        $this->primaire = School::create(['name' => 'Primary', 'code' => 'EBPS', 'type' => 'primaire', 'is_active' => true]);
        $this->college = School::create(['name' => 'College', 'code' => 'EBTC', 'type' => 'secondaire', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->college->id, 'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');
    }

    /** @param list<array<string, mixed>> $lignes */
    private function fichier(array $lignes, bool $avecCategorie = true): UploadedFile
    {
        $entetes = ['IDEleves', 'nom_eleves', 'sexe_eleves', 'etat_eleves', 'Nom_classe'];

        if ($avecCategorie) {
            $entetes[] = 'categorie_ecole';
        }

        $tableur = new Spreadsheet;
        $feuille = $tableur->getActiveSheet();
        $feuille->fromArray($entetes, null, 'A1');

        foreach (array_values($lignes) as $index => $ligne) {
            $valeurs = [
                $ligne['matricule'], $ligne['nom'], $ligne['sexe'] ?? 'F', 'Actif', $ligne['classe'] ?? '',
            ];

            if ($avecCategorie) {
                $valeurs[] = $ligne['categorie'] ?? '';
            }

            $feuille->fromArray($valeurs, null, 'A'.($index + 2));
        }

        $chemin = tempnam(sys_get_temp_dir(), 'eleves').'.xlsx';
        (new Xlsx($tableur))->save($chemin);

        return new UploadedFile($chemin, 'situation.xlsx', null, null, true);
    }

    /** Pas de X-School-Id : mode agrégé, comme un super admin qui importe sans se fixer sur une école. */
    private function importer(UploadedFile $fichier): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->superAdmin, 'sanctum')->post('/api/v1/eleves/import', ['file' => $fichier]);
    }

    public function test_chaque_ligne_rejoint_son_ecole_d_apres_la_categorie(): void
    {
        $fichier = $this->fichier([
            ['matricule' => 'M1', 'nom' => 'ELEVE MATERNELLE', 'categorie' => 'maternelle'],
            ['matricule' => 'P1', 'nom' => 'ELEVE PRIMAIRE', 'categorie' => 'primaire'],
            ['matricule' => 'S1', 'nom' => 'ELEVE SECONDAIRE', 'categorie' => 'secondaire technique'],
        ]);

        $this->importer($fichier)->assertOk()->assertJsonPath('data.imported', 3);

        $this->assertSame($this->maternelle->id, Eleve::where('matricule', 'M1')->value('school_id'));
        $this->assertSame($this->primaire->id, Eleve::where('matricule', 'P1')->value('school_id'));
        $this->assertSame($this->college->id, Eleve::where('matricule', 'S1')->value('school_id'));
    }

    /** Une classe n'existe qu'à son école : l'élève n'est pas dupliqué ailleurs sans classe. */
    public function test_les_totaux_ne_comptent_pas_chaque_ligne_trois_fois(): void
    {
        $annee = AnneeScolaire::create([
            'school_id' => $this->primaire->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);
        Classe::create(['school_id' => $this->primaire->id, 'annee_scolaire_id' => $annee->id, 'nom' => 'CLASS 1-A']);

        $fichier = $this->fichier([
            ['matricule' => 'P2', 'nom' => 'DEUXIEME ELEVE', 'categorie' => 'primaire', 'classe' => 'CLASS 1-A'],
        ]);

        $reponse = $this->importer($fichier)->assertOk();

        $this->assertSame(1, $reponse->json('data.imported'));
        $this->assertSame(1, Eleve::where('matricule', 'P2')->count());
    }

    /** Sans colonne categorie_ecole, on refuse de deviner plutôt que de dupliquer chaque élève dans les trois écoles. */
    public function test_sans_colonne_categorie_le_mode_agrege_est_refuse(): void
    {
        $fichier = $this->fichier([['matricule' => 'X1', 'nom' => 'SANS CATEGORIE']], avecCategorie: false);

        $this->importer($fichier)->assertStatus(422);

        $this->assertSame(0, Eleve::count());
    }

    /** Une ligne dont la catégorie ne correspond à aucune école du complexe est comptée, pas silencieusement perdue. */
    public function test_une_categorie_non_reconnue_est_comptee_ignoree(): void
    {
        $fichier = $this->fichier([
            ['matricule' => 'Z1', 'nom' => 'CATEGORIE INCONNUE', 'categorie' => 'universite'],
        ]);

        $this->importer($fichier)
            ->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.ignored', 1);
    }

    /** Un compte fixé à une école (X-School-Id) garde l'ancien comportement : un seul import, pas de répartition. */
    public function test_avec_x_school_id_l_import_reste_mono_ecole(): void
    {
        $fichier = $this->fichier([
            ['matricule' => 'M9', 'nom' => 'ELEVE MATERNELLE', 'categorie' => 'maternelle'],
            ['matricule' => 'P9', 'nom' => 'ELEVE PRIMAIRE', 'categorie' => 'primaire'],
        ]);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->withHeader('X-School-Id', $this->maternelle->id)
            ->post('/api/v1/eleves/import', ['file' => $fichier])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $this->assertSame(1, Eleve::count());
        $this->assertSame($this->maternelle->id, Eleve::where('matricule', 'M9')->value('school_id'));
    }
}
