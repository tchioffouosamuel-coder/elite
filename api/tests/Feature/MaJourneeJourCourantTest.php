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
use App\Models\ProgressionItem;
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
 * Un enseignant ne déclare (leçons + appel) que la séance du jour — passé ou
 * futur, il consulte sans pouvoir modifier. La direction en est dispensée,
 * comme pour le QR (cf. MaJourneeQrRequisTest).
 */
class MaJourneeJourCourantTest extends TestCase
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
            'name' => 'Elites Test', 'code' => 'ET2', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $this->classe = Classe::create([
            'school_id' => $this->school->id, 'nom' => '5e A', 'qr_token' => 'TOKEN-SALLE-5EA',
        ]);
        Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'ELEVE UN', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Histoire', 'statut' => 'actif']);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant',
        ]);
        $fonction->synchroniserPermissions(RolePermissionSeeder::ROLE_PERMISSIONS['enseignant']);

        $prof = User::create([
            'name' => 'Prof Histoire', 'email' => 'prof.histoire@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $prof->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'Prof Histoire', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $prof = $prof->fresh();

        $this->classeMatiere = ClasseMatiere::create([
            'classe_id' => $this->classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $prof->personnel->id, 'statut' => 'actif',
        ]);

        // Créneau couvrant toute la journée, tous les jours de la semaine :
        // les tests portent aussi bien sur hier que sur demain, quel que soit
        // le jour où la suite tourne.
        foreach (range(1, 7) as $jour) {
            EmploiDuTemps::create([
                'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
                'classe_matiere_id' => $this->classeMatiere->id,
                'jour' => $jour, 'heure_debut' => '00:00', 'heure_fin' => '23:59',
            ]);
        }
    }

    private function prof(): User
    {
        return User::where('email', 'prof.histoire@test.local')->firstOrFail();
    }

    private function corpsAppel(array $extra = []): array
    {
        return [
            'lecons' => [],
            'appel' => [],
            'qr_token' => 'TOKEN-SALLE-5EA',
            ...$extra,
        ];
    }

    public function test_un_enseignant_ne_peut_pas_declarer_une_seance_passee(): void
    {
        $hier = now()->subDay()->format('Y-m-d');

        $this->actingAs($this->prof(), 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}?date={$hier}", $this->corpsAppel())
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'seance_hors_jour');

        $seance = Seance::where('classe_matiere_id', $this->classeMatiere->id)->whereDate('date_seance', $hier)->first();
        $this->assertNotSame('effectuee', $seance?->statut);
    }

    public function test_un_enseignant_ne_peut_pas_declarer_une_seance_future(): void
    {
        $demain = now()->addDay()->format('Y-m-d');

        $this->actingAs($this->prof(), 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}?date={$demain}", $this->corpsAppel())
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'seance_hors_jour');
    }

    public function test_un_enseignant_peut_toujours_declarer_aujourdhui(): void
    {
        $this->actingAs($this->prof(), 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}", $this->corpsAppel())
            ->assertOk();

        $seance = Seance::where('classe_matiere_id', $this->classeMatiere->id)->firstOrFail();
        $this->assertSame('effectuee', $seance->statut);
    }

    public function test_la_feuille_du_jour_indique_si_la_seance_est_aujourdhui(): void
    {
        $hier = now()->subDay()->format('Y-m-d');

        $reponseAujourdhui = $this->actingAs($this->prof(), 'sanctum')
            ->getJson("/api/v1/ma-journee/{$this->classeMatiere->id}")
            ->assertOk();
        $this->assertTrue($reponseAujourdhui->json('data.seance.aujourdhui'));

        $reponseHier = $this->actingAs($this->prof(), 'sanctum')
            ->getJson("/api/v1/ma-journee/{$this->classeMatiere->id}?date={$hier}")
            ->assertOk();
        $this->assertFalse($reponseHier->json('data.seance.aujourdhui'));
    }

    public function test_la_direction_peut_declarer_une_seance_passee(): void
    {
        $hier = now()->subDay()->format('Y-m-d');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}?date={$hier}", [
                'lecons' => [],
                'appel' => [],
            ])
            ->assertOk();

        $seance = Seance::where('classe_matiere_id', $this->classeMatiere->id)->firstOrFail();
        $this->assertSame('effectuee', $seance->statut);
        $this->assertSame($hier, $seance->date_seance->format('Y-m-d'));
    }

    public function test_les_details_d_une_lecon_sont_accessibles_a_l_enseignant_concerne(): void
    {
        $lecon = ProgressionItem::create([
            'classe_matiere_id' => $this->classeMatiere->id,
            'type' => 'lecon',
            'titre' => 'La Révolution française',
            'ordre' => 1,
            'expected_learning_outcomes' => 'Situer les grandes dates.',
            'teaching_aids' => 'Carte, frise chronologique.',
        ]);

        $reponse = $this->actingAs($this->prof(), 'sanctum')
            ->getJson("/api/v1/ma-journee/{$this->classeMatiere->id}/lecons/{$lecon->id}")
            ->assertOk();

        $reponse->assertJsonPath('data.titre', 'La Révolution française');
        $reponse->assertJsonPath('data.expected_learning_outcomes', 'Situer les grandes dates.');
        $reponse->assertJsonPath('data.teaching_aids', 'Carte, frise chronologique.');
    }

    public function test_une_lecon_d_une_autre_affectation_est_introuvable(): void
    {
        $autreMatiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Géographie', 'statut' => 'actif']);
        $autreClasseMatiere = ClasseMatiere::create([
            'classe_id' => $this->classe->id, 'matiere_id' => $autreMatiere->id,
            'personnel_id' => $this->prof()->personnel->id, 'statut' => 'actif',
        ]);
        $leconAutrePart = ProgressionItem::create([
            'classe_matiere_id' => $autreClasseMatiere->id,
            'type' => 'lecon',
            'titre' => 'Le relief africain',
            'ordre' => 1,
        ]);

        $this->actingAs($this->prof(), 'sanctum')
            ->getJson("/api/v1/ma-journee/{$this->classeMatiere->id}/lecons/{$leconAutrePart->id}")
            ->assertNotFound();
    }
}
