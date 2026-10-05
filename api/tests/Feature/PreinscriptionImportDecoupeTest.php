<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\Setting;
use App\Models\Tuteur;
use App\Models\User;
use App\Models\Versement;
use App\Services\PreinscriptionService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Import massif de préinscriptions découpé en petits lots — le même correctif
 * que {@see EleveImportDecoupeTest} pour la même raison : un fichier de
 * plusieurs centaines de lignes envoyé en un seul appel à
 * `POST /preinscriptions/import` dépassait le délai d'exécution du serveur en
 * production (observé en conditions réelles : requête tuée en plein milieu,
 * rien d'importé, aucune erreur visible côté client faute de réponse
 * exploitable). Chaque lot rejoue `PreinscriptionImport` inchangée sur un
 * fichier bien plus petit, via sa propre requête HTTP.
 */
class PreinscriptionImportDecoupeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private Tuteur $tuteur;
    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);

        AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2']);
        $this->tuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Mballa Jean', 'telephone' => '699000000']);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    /** `$nombreLignes` élèves déjà existants (matricule E1..EN, tuteur déjà attaché) — le fichier ne fait que confirmer leur réinscription en CM2. */
    private function fichierReinscriptions(int $nombreLignes): UploadedFile
    {
        $tableur = new Spreadsheet;
        $feuille = $tableur->getActiveSheet();
        $feuille->fromArray(['IDEleves', 'nom_eleves', 'Nom_classe'], null, 'A1');

        for ($i = 1; $i <= $nombreLignes; $i++) {
            $eleve = Eleve::create([
                'school_id' => $this->school->id, 'matricule' => "E{$i}", 'nom_complet' => "ELEVE {$i}",
                'sexe' => $i % 2 === 0 ? 'F' : 'M', 'date_naissance' => '2014-01-01', 'statut' => 'actif',
            ]);
            $eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);

            $feuille->fromArray(["E{$i}", "ELEVE {$i}", 'CM2'], null, 'A'.($i + 1));
        }

        $chemin = tempnam(sys_get_temp_dir(), 'preinscriptions').'.xlsx';
        (new Xlsx($tableur))->save($chemin);

        return new UploadedFile($chemin, 'situation.xlsx', null, null, true);
    }

    private function preparer(UploadedFile $fichier): array
    {
        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/preinscriptions/import/preparer', ['file' => $fichier])
            ->assertOk();

        return $reponse->json('data');
    }

    private function traiterLot(string $token, int $index): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson("/api/v1/preinscriptions/import/traiter/{$token}", ['index' => $index]);
    }

    public function test_un_fichier_de_130_lignes_se_decoupe_en_trois_lots_de_60(): void
    {
        ['token' => $token, 'lots' => $lots] = $this->preparer($this->fichierReinscriptions(130));

        $this->assertSame(3, $lots);

        $totalImporte = 0;
        for ($i = 0; $i < $lots; $i++) {
            $reponse = $this->traiterLot($token, $i)->assertOk();
            $totalImporte += $reponse->json('data.imported');
            $this->assertSame($i === $lots - 1, $reponse->json('data.dernier'));
        }

        $this->assertSame(130, $totalImporte);
        $this->assertSame($this->classe->id, Eleve::where('matricule', 'E1')->first()->classe_id);
        $this->assertSame($this->classe->id, Eleve::where('matricule', 'E130')->first()->classe_id);
    }

    /**
     * Un élève répété dans un lot ultérieur (sa première ligne étant dans un
     * lot précédent) doit être reconnu comme paiement supplémentaire : le
     * rang d'apparition est calculé sur le fichier entier, avant découpe.
     */
    public function test_une_ligne_repetee_dans_un_autre_lot_devient_un_paiement(): void
    {
        $tableur = new Spreadsheet;
        $feuille = $tableur->getActiveSheet();
        $feuille->fromArray(['IDEleves', 'nom_eleves', 'Nom_classe', 'MONTANT_SCOLARITE'], null, 'A1');

        for ($i = 1; $i <= 60; $i++) {
            $eleve = Eleve::create([
                'school_id' => $this->school->id, 'matricule' => "E{$i}", 'nom_complet' => "ELEVE {$i}",
                'sexe' => 'M', 'date_naissance' => '2014-01-01', 'statut' => 'actif',
            ]);
            $eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);
            $feuille->fromArray(["E{$i}", "ELEVE {$i}", 'CM2', $i === 1 ? 10000 : null], null, 'A'.($i + 1));
        }
        // Ligne 62 : deuxième lot, même élève que la ligne 2.
        $feuille->fromArray(['E1', 'ELEVE 1', 'CM2', 4000], null, 'A62');

        $chemin = tempnam(sys_get_temp_dir(), 'preinscriptions').'.xlsx';
        (new Xlsx($tableur))->save($chemin);

        ['token' => $token, 'lots' => $lots] = $this->preparer(new UploadedFile($chemin, 'situation.xlsx', null, null, true));
        $this->assertSame(2, $lots);

        $this->traiterLot($token, 0)->assertOk()->assertJsonPath('data.imported', 60);
        $this->traiterLot($token, 1)->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.paiements', 1)
            ->assertJsonPath('data.failed', 0);

        $eleve = Eleve::where('matricule', 'E1')->first();
        $this->assertSame(1, Preinscription::where('eleve_id', $eleve->id)->count());
        $this->assertSame(
            [10000, 4000],
            Versement::whereHas('dossier', fn($q) => $q->where('eleve_id', $eleve->id))->orderBy('id')->pluck('montant')->all(),
        );
    }

    /** Le lot déjà traité est supprimé : le rejouer échoue proprement plutôt que de repasser deux fois la même ligne. */
    public function test_rejouer_un_lot_deja_traite_echoue(): void
    {
        ['token' => $token] = $this->preparer($this->fichierReinscriptions(10));

        $this->traiterLot($token, 0)->assertOk()->assertJsonPath('data.imported', 10);

        $this->traiterLot($token, 0)
            ->assertStatus(422)
            ->assertJsonPath('message', "Ce lot est introuvable — il a peut-être déjà été traité, ou l'import a expiré.");
    }

    /** Le dossier temporaire ne doit rien laisser traîner une fois le dernier lot passé. */
    public function test_le_dossier_temporaire_est_nettoye_apres_le_dernier_lot(): void
    {
        ['token' => $token, 'lots' => $lots] = $this->preparer($this->fichierReinscriptions(5));

        for ($i = 0; $i < $lots; $i++) {
            $this->traiterLot($token, $i)->assertOk();
        }

        $this->assertDirectoryDoesNotExist(storage_path('app/private/imports-preinscriptions/'.$token));
    }

    /**
     * Les parents voient, sous leur situation financière, la date du dernier
     * import massif : c'est jusque-là que les versements de la campagne ont
     * été reportés dans l'application.
     */
    public function test_un_lot_traite_date_la_derniere_mise_a_jour_vue_par_le_parent(): void
    {
        $this->travelTo('2026-10-04 14:32:00');
        ['token' => $token] = $this->preparer($this->fichierReinscriptions(2));
        $this->traiterLot($token, 0)->assertOk();
        $this->travelBack();

        $this->assertNotNull(Setting::get($this->school->id, PreinscriptionService::CLE_DERNIER_IMPORT));

        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        $parent = User::create([
            'name' => 'Mballa Jean', 'email' => 'mballa@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $parent->assignRole('parent');
        $parent->givePermissionTo('eleves.view');
        $this->tuteur->update(['user_id' => $parent->id]);

        $eleve = Eleve::where('matricule', 'E1')->firstOrFail();
        $reponse = $this->actingAs($parent, 'sanctum')
            ->getJson("/api/v1/parent/enfants/{$eleve->id}/finance")
            ->assertOk();

        $this->assertStringStartsWith('2026-10-04T14:32:00', $reponse->json('data.derniere_mise_a_jour'));
    }
}
