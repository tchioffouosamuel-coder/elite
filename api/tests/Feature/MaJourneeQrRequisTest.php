<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Un enseignant doit avoir scanné le QR code de la salle pour que sa
 * validation d'appel/leçons passe — la preuve qu'il était en classe. La
 * direction (super_admin, admin_etablissement, censeur_sg) en est dispensée,
 * cf. `User::doitScannerQrPourValiderAppel()`.
 */
class MaJourneeQrRequisTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    private ClasseMatiere $classeMatiere;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true,
        ]);

        $this->classe = Classe::create([
            'school_id' => $this->school->id, 'nom' => '6e A', 'qr_token' => 'TOKEN-SALLE-6EA',
        ]);
        Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'ELEVE UN', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques', 'statut' => 'actif']);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $prof = $this->enseignant('Prof Math', 'prof.math@test.local');
        $this->classeMatiere = ClasseMatiere::create([
            'classe_id' => $this->classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);

        // Créneau couvrant toute la journée (aujourd'hui, quel que soit le
        // jour où le test tourne) : `seanceDuJour()` a besoin d'un créneau
        // du jour pour matérialiser la séance sur laquelle porte l'appel.
        EmploiDuTemps::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'classe_matiere_id' => $this->classeMatiere->id,
            'jour' => now()->dayOfWeekIso, 'heure_debut' => '00:00', 'heure_fin' => '23:59',
        ]);
    }

    private function enseignant(string $nom, string $email): User
    {
        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant',
        ]);
        $fonction->synchroniserPermissions(RolePermissionSeeder::ROLE_PERMISSIONS['enseignant']);

        $user = User::create([
            'name' => $nom, 'email' => $email, 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    private function prof(): User
    {
        return User::where('email', 'prof.math@test.local')->firstOrFail();
    }

    private function corpsAppel(array $extra = []): array
    {
        return [
            'lecons' => [],
            'appel' => [],
            ...$extra,
        ];
    }

    public function test_un_enseignant_sans_scan_est_refuse(): void
    {
        $this->actingAs($this->prof(), 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}", $this->corpsAppel())
            ->assertForbidden();

        $this->assertDatabaseMissing('seances', ['classe_matiere_id' => $this->classeMatiere->id, 'statut' => 'effectuee']);
    }

    public function test_un_enseignant_avec_le_mauvais_token_est_refuse(): void
    {
        $this->actingAs($this->prof(), 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}", $this->corpsAppel([
                'qr_token' => 'MAUVAIS-TOKEN',
            ]))
            ->assertForbidden();
    }

    public function test_un_enseignant_avec_le_bon_token_est_accepte_et_horodate(): void
    {
        $this->actingAs($this->prof(), 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}", $this->corpsAppel([
                'qr_token' => 'TOKEN-SALLE-6EA',
            ]))
            ->assertOk();

        $seance = Seance::where('classe_matiere_id', $this->classeMatiere->id)->firstOrFail();
        $this->assertSame('effectuee', $seance->statut);
        $this->assertNotNull($seance->qr_verifie_le);
    }

    public function test_la_direction_n_a_pas_besoin_de_scanner(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}", $this->corpsAppel())
            ->assertOk();

        $seance = Seance::where('classe_matiere_id', $this->classeMatiere->id)->firstOrFail();
        $this->assertSame('effectuee', $seance->statut);
        $this->assertNull($seance->qr_verifie_le);
    }
}
