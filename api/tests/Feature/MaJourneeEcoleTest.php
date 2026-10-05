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
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * « Ma journée / école » : la vue transverse que la direction consulte au
 * quotidien — tous les cours prévus, pas seulement les siens (cf.
 * MaJourneeJourCourantTest pour le pendant enseignant).
 */
class MaJourneeEcoleTest extends TestCase
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
            'name' => 'Elites Test', 'code' => 'ET3', 'type' => 'secondaire', 'is_active' => true,
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
        $fonction->synchroniserPermissions(RolePermissionSeeder::permissionsDuRole('enseignant'));

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

        // Créneau couvrant toute la journée, tous les jours de la semaine.
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

    public function test_un_enseignant_ordinaire_n_a_pas_acces_a_la_vue_transverse(): void
    {
        $this->actingAs($this->prof(), 'sanctum')
            ->getJson('/api/v1/ma-journee/ecole')
            ->assertForbidden();
    }

    public function test_la_direction_voit_tous_les_cours_du_jour_avec_leur_statut(): void
    {
        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/ma-journee/ecole')
            ->assertOk();

        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.classe_matiere_id', $this->classeMatiere->id);
        $reponse->assertJsonPath('data.0.classe', '5e A');
        $reponse->assertJsonPath('data.0.matiere', 'Histoire');
        $reponse->assertJsonPath('data.0.statut', 'prevue');
        $reponse->assertJsonPath('data.0.lecons_traitees', 0);
    }

    public function test_le_statut_reflete_une_seance_deja_declaree(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}", ['lecons' => [], 'appel' => []])
            ->assertOk();

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/ma-journee/ecole')
            ->assertOk();

        $reponse->assertJsonPath('data.0.statut', 'effectuee');
    }

    /**
     * Les séances sont pré-générées « prevue » à l'ouverture d'un trimestre
     * (cf. EmploiDuTempsService::genererSeances()) : leur simple existence ne
     * doit pas masquer un cours dont l'heure de fin est passée sans appel.
     */
    public function test_une_seance_pregeneree_non_declaree_passe_en_retard_apres_l_heure_de_fin(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(16, 5));

        EmploiDuTemps::where('classe_matiere_id', $this->classeMatiere->id)
            ->update(['heure_debut' => '07:30', 'heure_fin' => '09:10']);

        Seance::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->classe->id,
            'classe_matiere_id' => $this->classeMatiere->id,
            'date_seance' => Carbon::today()->toDateString(),
            'heure_debut' => '07:30',
            'heure_fin' => '09:10',
            'statut' => 'prevue',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/ma-journee/ecole')
            ->assertOk()
            ->assertJsonPath('data.0.statut', 'en_retard');
    }

    public function test_un_cours_a_venir_dans_la_journee_reste_prevu(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(8, 0));

        EmploiDuTemps::where('classe_matiere_id', $this->classeMatiere->id)
            ->update(['heure_debut' => '07:30', 'heure_fin' => '09:10']);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/ma-journee/ecole')
            ->assertOk()
            ->assertJsonPath('data.0.statut', 'prevue');
    }

    public function test_un_cours_non_declare_d_une_date_passee_est_en_retard(): void
    {
        $hier = Carbon::today()->subDay()->toDateString();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/ma-journee/ecole?date='.$hier)
            ->assertOk()
            ->assertJsonPath('data.0.statut', 'en_retard');
    }
}
