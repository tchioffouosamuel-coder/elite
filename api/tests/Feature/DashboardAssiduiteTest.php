<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Presence;
use App\Models\School;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Carte « Vue d'ensemble » du tableau de bord : taux de présence et
 * absences relevés à l'appel, sur le mois, le trimestre ou l'année en cours.
 */
class DashboardAssiduiteTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    private ClasseMatiere $classeMatiere;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        // Mercredi 15 octobre 2025 : en plein premier trimestre.
        Carbon::setTestNow(Carbon::parse('2025-10-15 10:00:00'));

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

        $this->school = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30', 'is_active' => true,
        ]);
        Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2025-10-01', 'date_fin' => '2025-12-20', 'is_active' => true,
        ]);
        $niveau = Niveau::create(['code' => '6E', 'name_fr' => '6ème', 'name_en' => 'Form 1', 'school_id' => $this->school->id]);
        $this->classe = Classe::create([
            'school_id' => $this->school->id, 'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $annee->id, 'nom' => '6ème A',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques', 'statut' => 'actif']);
        $this->classeMatiere = ClasseMatiere::create([
            'classe_id' => $this->classe->id, 'matiere_id' => $matiere->id, 'statut' => 'actif',
        ]);

        $this->agent = User::create([
            'name' => 'Directeur', 'email' => 'directeur@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->agent->givePermissionTo('dashboard.view');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  list<string>  $statuts */
    private function appel(string $date, array $statuts, string $statutSeance = 'effectuee'): void
    {
        $seance = Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'classe_matiere_id' => $this->classeMatiere->id,
            'date_seance' => $date, 'heure_debut' => '08:00', 'heure_fin' => '09:00',
            'statut' => $statutSeance,
        ]);

        foreach ($statuts as $i => $statut) {
            $eleve = Eleve::create([
                'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
                'nom_complet' => "Élève {$date} {$i}", 'sexe' => 'M', 'statut' => 'actif',
            ]);
            Presence::create(['seance_id' => $seance->id, 'eleve_id' => $eleve->id, 'statut' => $statut]);
        }
    }

    public function test_le_taux_de_presence_compte_les_retards_comme_presents_et_les_renvois_comme_absents(): void
    {
        // Ce mois-ci : 3 présents, 1 retard, 1 absent, 1 renvoyé.
        $this->appel('2025-10-06', ['present', 'present', 'present', 'retard', 'absent', 'renvoye']);

        $this->actingAs($this->agent, 'sanctum')
            ->getJson('/api/v1/dashboard/assiduite?periode=mois')
            ->assertOk()
            ->assertJsonPath('data.periode', 'mois')
            ->assertJsonPath('data.pointages', 6)
            ->assertJsonPath('data.presences', 4)
            ->assertJsonPath('data.absences', 2)
            ->assertJsonPath('data.taux_presence', 66.7);
    }

    public function test_chaque_periode_ne_compte_que_ses_propres_appels(): void
    {
        $this->appel('2025-09-10', ['absent', 'absent']);   // année, hors trimestre et hors mois
        $this->appel('2025-10-02', ['present']);            // mois, trimestre et année
        $this->appel('2025-10-14', ['present', 'absent']);  // mois, trimestre et année
        $this->appel('2025-10-14', ['absent'], 'prevue');   // appel jamais fait : ignoré
        $this->appel('2025-10-20', ['absent']);             // dans le futur : ignoré

        $client = $this->actingAs($this->agent, 'sanctum');

        $client->getJson('/api/v1/dashboard/assiduite?periode=mois')
            ->assertJsonPath('data.pointages', 3)
            ->assertJsonPath('data.absences', 1);

        $client->getJson('/api/v1/dashboard/assiduite?periode=trimestre')
            ->assertJsonPath('data.pointages', 3)
            ->assertJsonPath('data.absences', 1);

        $client->getJson('/api/v1/dashboard/assiduite?periode=annee')
            ->assertJsonPath('data.pointages', 5)
            ->assertJsonPath('data.absences', 3)
            ->assertJsonPath('data.taux_presence', 40);
    }

    public function test_sans_appel_le_taux_est_nul_plutot_que_cent_pour_cent(): void
    {
        $this->actingAs($this->agent, 'sanctum')
            ->getJson('/api/v1/dashboard/assiduite')
            ->assertOk()
            ->assertJsonPath('data.periode', 'mois')
            ->assertJsonPath('data.pointages', 0)
            ->assertJsonPath('data.taux_presence', null);
    }

    public function test_une_periode_inconnue_est_refusee(): void
    {
        $this->actingAs($this->agent, 'sanctum')
            ->getJson('/api/v1/dashboard/assiduite?periode=semaine')
            ->assertStatus(422);
    }

    public function test_la_route_exige_le_privilege_du_tableau_de_bord(): void
    {
        $sansDroit = User::create([
            'name' => 'Agent', 'email' => 'agent@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->actingAs($sansDroit, 'sanctum')
            ->getJson('/api/v1/dashboard/assiduite')
            ->assertForbidden();
    }
}
