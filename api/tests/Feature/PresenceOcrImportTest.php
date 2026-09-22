<?php

namespace Tests\Feature;

use App\Models\Personnel;
use App\Models\PresencePersonnelJournaliere;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * L'étape OCR elle-même (lecture de la photo) suppose le binaire tesseract
 * installé sur la machine qui exécute les tests — elle n'est pas couverte
 * ici. Ce test porte sur ce qui suit : la confirmation des lignes relues et
 * corrigées par l'utilisateur dans la modale de prévisualisation, qui doit
 * produire le même résultat que l'import Excel (voir PersonnelImportTest).
 */
class PresenceOcrImportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private Personnel $agborn;

    private Personnel $keugne;

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

        $this->agborn = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'AGBORNDE CATHERINE BESONG', 'statut' => 'actif',
        ]);
        $this->keugne = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'KENGNE NEIL', 'statut' => 'actif',
        ]);
    }

    private function confirmer(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/personnels/presences-journalieres/import-ocr', $payload);
    }

    public function test_les_lignes_confirmees_creent_les_presences(): void
    {
        $reponse = $this->confirmer([
            'date' => '2026-09-22',
            'lignes' => [
                ['personnel_id' => $this->agborn->id, 'heure_arrivee' => '07:45', 'heure_depart' => '15:30'],
                ['personnel_id' => $this->keugne->id, 'heure_arrivee' => '08:00', 'heure_depart' => null],
            ],
        ]);

        $reponse->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('presences_personnel_journalieres', [
            'personnel_id' => $this->agborn->id,
            'date_presence' => '2026-09-22 00:00:00',
            'heure_arrivee' => '07:45',
            'heure_depart' => '15:30',
            'source' => 'import_ocr',
        ]);
        $this->assertDatabaseHas('presences_personnel_journalieres', [
            'personnel_id' => $this->keugne->id,
            'date_presence' => '2026-09-22 00:00:00',
            'heure_arrivee' => '08:00',
            'heure_depart' => null,
        ]);
    }

    public function test_une_ligne_deja_presente_est_mise_a_jour_plutot_que_dupliquee(): void
    {
        PresencePersonnelJournaliere::create([
            'school_id' => $this->school->id,
            'personnel_id' => $this->agborn->id,
            'date_presence' => '2026-09-22',
            'heure_arrivee' => '07:00',
            'heure_depart' => null,
            'source' => 'import_ocr',
        ]);

        $reponse = $this->confirmer([
            'date' => '2026-09-22',
            'lignes' => [
                ['personnel_id' => $this->agborn->id, 'heure_arrivee' => '07:45', 'heure_depart' => '15:30'],
            ],
        ]);

        $reponse->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.updated', 1);

        $this->assertSame(1, PresencePersonnelJournaliere::where('personnel_id', $this->agborn->id)->count());
    }

    public function test_une_heure_de_depart_anterieure_a_larrivee_est_rejetee(): void
    {
        $reponse = $this->confirmer([
            'date' => '2026-09-22',
            'lignes' => [
                ['personnel_id' => $this->agborn->id, 'heure_arrivee' => '15:30', 'heure_depart' => '07:45'],
            ],
        ]);

        $reponse->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.failed', 1);

        $this->assertDatabaseMissing('presences_personnel_journalieres', [
            'personnel_id' => $this->agborn->id,
            'date_presence' => '2026-09-22',
        ]);
    }

    public function test_un_agent_inconnu_est_rejete_sans_bloquer_les_autres_lignes(): void
    {
        $reponse = $this->confirmer([
            'date' => '2026-09-22',
            'lignes' => [
                ['personnel_id' => 999999, 'nom_complet' => 'Introuvable', 'heure_arrivee' => '08:00', 'heure_depart' => null],
                ['personnel_id' => $this->agborn->id, 'heure_arrivee' => '08:00', 'heure_depart' => null],
            ],
        ]);

        $reponse->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.failed', 1);
    }
}
