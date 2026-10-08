<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JournalAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function superAdmin(): User
    {
        $user = User::create([
            'name' => 'Dev Root', 'email' => 'dev@test.local', 'password' => 'secret123',
            'school_id' => null, 'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_une_connexion_echouee_est_journalisee_sans_le_mot_de_passe(): void
    {
        $user = $this->superAdmin();

        $this->postJson('/api/v1/auth/login', ['identifiant' => 'dev@test.local', 'password' => 'mauvais'])
            ->assertStatus(401);

        $log = AuditLog::sole();
        $this->assertSame('connexion_echouee', $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Dev Root', $log->user_nom);
        $this->assertSame(401, $log->statut_http);
        $this->assertSame('***', $log->donnees['password']);
        $this->assertSame('dev@test.local', $log->donnees['identifiant']);
    }

    public function test_une_connexion_reussie_est_attribuee_au_compte(): void
    {
        $user = $this->superAdmin();

        $this->postJson('/api/v1/auth/login', ['identifiant' => 'dev@test.local', 'password' => 'secret123'])
            ->assertOk();

        $log = AuditLog::where('action', 'connexion')->sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('auth', $log->module);
        $this->assertNotNull($log->created_at);
    }

    public function test_une_consultation_est_journalisee_avec_son_auteur(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk();

        $log = AuditLog::sole();
        $this->assertSame('consultation', $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('GET', $log->methode);
        $this->assertSame('/api/v1/auth/me', $log->url);
        $this->assertNull($log->donnees);
    }

    public function test_une_modification_garde_les_valeurs_avant_et_apres(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->putJson('/api/v1/auth/profil', [
            'name' => 'Dev Renommé', 'email' => 'dev@test.local',
        ])->assertOk();

        $log = AuditLog::sole();
        $this->assertSame('modification', $log->action);
        $this->assertSame($user->id, $log->user_id);

        $changement = collect($log->changements)->firstWhere('modele', 'User');
        $this->assertSame('updated', $changement['operation']);
        $this->assertSame(['name' => 'Dev Root'], $changement['avant']);
        $this->assertSame(['name' => 'Dev Renommé'], $changement['apres']);
    }

    public function test_les_appels_de_fond_ne_sont_pas_journalises(): void
    {
        $user = $this->superAdmin();
        School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);

        $this->actingAs($user)->getJson('/api/v1/notifications/non-lues');
        $this->actingAs($user)->getJson('/api/v1/audit?actualisation_auto=1')->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_la_console_est_reservee_au_super_admin(): void
    {
        $ecole = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $agent = User::create([
            'name' => 'Agent', 'email' => 'agent@test.local', 'password' => 'secret123',
            'school_id' => $ecole->id, 'is_active' => true,
        ]);

        $this->actingAs($agent)->getJson('/api/v1/audit')->assertForbidden();
    }

    public function test_la_console_filtre_par_action_et_utilisateur(): void
    {
        $user = $this->superAdmin();
        School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);

        $this->actingAs($user)->getJson('/api/v1/auth/me');
        $this->actingAs($user)->putJson('/api/v1/auth/profil', ['name' => 'X', 'email' => 'dev@test.local']);

        $reponse = $this->actingAs($user)->getJson("/api/v1/audit?action=modification&user_id={$user->id}&actualisation_auto=1")
            ->assertOk();

        $this->assertCount(1, $reponse->json('data'));
        $this->assertSame('modification', $reponse->json('data.0.action'));

        $detail = $this->actingAs($user)->getJson('/api/v1/audit/'.$reponse->json('data.0.id'))->assertOk();
        $this->assertNotEmpty($detail->json('data.changements'));
    }

    public function test_le_journal_associe_les_requetes_sur_un_eleve_a_son_nom(): void
    {
        $user = $this->superAdmin();
        $school = School::create([
            'name' => 'Elites Secondaire',
            'code' => 'ES',
            'type' => 'secondaire',
            'is_active' => true,
        ]);
        $eleve = Eleve::create([
            'school_id' => $school->id,
            'matricule' => 'EL-16410',
            'nom_complet' => 'Awa Exemple',
            'sexe' => 'F',
            'statut' => 'actif',
        ]);
        $log = AuditLog::create([
            'created_at' => now(),
            'school_id' => $school->id,
            'user_id' => $user->id,
            'user_nom' => $user->name,
            'action' => 'consultation',
            'module' => 'parent',
            'route' => 'api.v1.parent.enfants.assiduite',
            'methode' => 'GET',
            'url' => "/api/v1/parent/enfants/{$eleve->id}/assiduite",
            'parametres' => ['eleveId' => $eleve->id],
            'statut_http' => 200,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/audit')
            ->assertOk()
            ->assertJsonPath('data.0.sujet', 'Awa Exemple');

        $this->getJson('/api/v1/audit/'.$log->id)
            ->assertOk()
            ->assertJsonPath('data.sujet', 'Awa Exemple');
    }

    public function test_les_notifications_peuvent_etre_masquees_dans_la_liste_les_stats_et_export(): void
    {
        $user = $this->superAdmin();
        School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $this->actingAs($user);
        config(['audit.actif' => false]);

        foreach (['notifications', 'classes', null] as $module) {
            AuditLog::create([
                'created_at' => now(),
                'user_id' => $user->id,
                'user_nom' => $user->name,
                'action' => 'consultation',
                'module' => $module,
                'methode' => 'GET',
                'url' => '/api/v1/'.($module ?? 'inconnu'),
                'statut_http' => 200,
            ]);
        }

        foreach (['', '&exclure_notifications=0', '&exclure_notifications=1'] as $filtre) {
            $masquees = $filtre === '&exclure_notifications=1';
            $total = $masquees ? 2 : 3;
            $modules = $masquees ? ['classes', null] : ['notifications', 'classes', null];

            $liste = $this->getJson('/api/v1/audit?per_page=1'.$filtre)->assertOk();
            $liste->assertJsonPath('meta.pagination.total', $total);
            $liste->assertJsonPath('data.0.module', null);
            $dernierePage = $this->getJson('/api/v1/audit?per_page=1&page='.$total.$filtre)->assertOk();
            $dernierePage->assertJsonPath('data.0.module', $masquees ? 'classes' : 'notifications');

            $stats = $this->getJson('/api/v1/audit/stats?'.$filtre)->assertOk();
            $stats->assertJsonPath('data.total', $total);
            $stats->assertJsonPath('data.par_action.consultation', $total);
            $stats->assertJsonPath('data.top_utilisateurs.0.total', $total);
            $this->assertEqualsCanonicalizing(array_filter($modules), array_column($stats->json('data.top_modules'), 'module'));

            $csv = $this->get('/api/v1/audit/export?'.$filtre)->assertOk()->streamedContent();
            $this->assertStringContainsString('/api/v1/classes', $csv);
            $this->assertStringContainsString('/api/v1/inconnu', $csv);
            if ($masquees) {
                $this->assertStringNotContainsString('/api/v1/notifications', $csv);
            } else {
                $this->assertStringContainsString('/api/v1/notifications', $csv);
            }
        }

        $this->assertSame(3, AuditLog::count());
    }
}
