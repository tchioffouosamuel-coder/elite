<?php

namespace Tests\Feature;

use App\Models\DesktopProvisioning;
use App\Models\DesktopProvisioningEcole;
use App\Models\Eleve;
use App\Models\School;
use App\Models\SyncOutbox;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fondations de la synchronisation desktop offline : provisioning
 * mono-utilisateur, `sync:pull` (delta + résolution « le plus récent
 * gagne »), `sync:push` (rejeu de l'outbox locale) et l'enregistrement
 * automatique de l'outbox par {@see \App\Http\Middleware\EnregistrerDansOutboxLocale}.
 */
class DesktopSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    // ----------------------------------------------------------- Provisioning

    public function test_provisionne_un_poste_et_tire_les_donnees_initiales(): void
    {
        Http::fake([
            '*/api/v1/sync*' => Http::response([
                'success' => true,
                'data' => [
                    'curseur' => '2026-01-01T00:00:00Z',
                    'complet' => true,
                    'donnees' => [
                        'eleves' => [[
                            'id' => 501,
                            'school_id' => 9,
                            'classe_id' => null,
                            'matricule' => 'EL-501',
                            'nom_complet' => 'ELEVE DISTANT',
                            'sexe' => 'M',
                            'statut' => 'actif',
                            'updated_at' => now()->subDay()->toIso8601ZuluString(),
                        ]],
                    ],
                    'suppressions' => [],
                ],
            ], 200),
        ]);

        $reponse = $this->postJson('/api/v1/desktop/provisionner', [
            'serveur_url' => 'https://distant.test',
            'token' => 'jeton-acces',
            'refresh_token' => 'jeton-refresh',
            'schools' => [['id' => 9, 'name' => 'École distante', 'code' => 'ED', 'type' => 'secondaire']],
            'user' => [
                'id' => 42,
                'name' => 'Titulaire Poste',
                'email' => 'titulaire@test.local',
                'school_id' => 9,
                'roles' => ['admin_etablissement'],
                'permissions' => ['eleves.view'],
            ],
        ]);

        $reponse->assertCreated();

        $this->assertDatabaseHas('users', ['id' => 42, 'name' => 'Titulaire Poste']);
        $this->assertDatabaseHas('desktop_provisioning', ['user_id' => 42, 'serveur_url' => 'https://distant.test']);
        $this->assertDatabaseHas('eleves', ['id' => 501, 'nom_complet' => 'ELEVE DISTANT']);

        $provisioning = DesktopProvisioning::actuelle();
        $ecole = $provisioning->ecoles()->where('school_id', 9)->firstOrFail();
        $this->assertNotNull($ecole->dernier_pull_le);
        $this->assertSame('2026-01-01T00:00:00Z', $ecole->curseur_sync);

        $this->assertTrue(User::find(42)->hasRole('admin_etablissement'));
        $this->assertTrue(User::find(42)->can('eleves.view'));
    }

    /** Un compte non borné à une seule école (super admin) réplique chacune de ses écoles, avec un curseur propre à chacune. */
    public function test_provisionne_plusieurs_ecoles_avec_un_curseur_chacune(): void
    {
        Http::fake([
            '*/api/v1/sync*' => Http::sequence()
                ->push($this->reponseSyncAvecUnEleve(id: 701, nom: 'ELEVE ECOLE 1', updatedAt: now(), schoolId: 11), 200)
                ->push($this->reponseSyncAvecUnEleve(id: 702, nom: 'ELEVE ECOLE 2', updatedAt: now(), schoolId: 12), 200),
        ]);

        $this->postJson('/api/v1/desktop/provisionner', [
            'serveur_url' => 'https://distant.test',
            'token' => 'jeton-acces',
            'refresh_token' => 'jeton-refresh',
            'schools' => [
                ['id' => 11, 'name' => 'École Un', 'code' => 'E1', 'type' => 'secondaire'],
                ['id' => 12, 'name' => 'École Deux', 'code' => 'E2', 'type' => 'secondaire'],
            ],
            'user' => [
                'id' => 77, 'name' => 'Super Admin Poste',
                'roles' => ['super_admin'], 'permissions' => [],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('schools', ['id' => 11, 'name' => 'École Un']);
        $this->assertDatabaseHas('schools', ['id' => 12, 'name' => 'École Deux']);
        $this->assertDatabaseHas('eleves', ['id' => 701, 'nom_complet' => 'ELEVE ECOLE 1']);
        $this->assertDatabaseHas('eleves', ['id' => 702, 'nom_complet' => 'ELEVE ECOLE 2']);

        $provisioning = DesktopProvisioning::actuelle();
        $this->assertSame(2, $provisioning->ecoles()->count());
        $this->assertNotNull($provisioning->ecoles()->where('school_id', 11)->first()->curseur_sync);
        $this->assertNotNull($provisioning->ecoles()->where('school_id', 12)->first()->curseur_sync);
    }

    public function test_refuse_un_second_provisioning(): void
    {
        $user = User::factory()->create();
        DesktopProvisioning::create([
            'user_id' => $user->id, 'serveur_url' => 'https://distant.test',
            'token' => 't', 'refresh_token' => 'r', 'provisionne_le' => now(),
        ]);

        $this->postJson('/api/v1/desktop/provisionner', [
            'serveur_url' => 'https://autre.test', 'token' => 't', 'refresh_token' => 'r',
            'user' => ['id' => 99, 'name' => 'Intrus'],
        ])->assertStatus(409);
    }

    public function test_session_authentifie_le_compte_du_poste_sans_mot_de_passe(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        DesktopProvisioning::create([
            'user_id' => $user->id, 'serveur_url' => 'https://distant.test',
            'token' => 't', 'refresh_token' => 'r', 'provisionne_le' => now(),
        ]);

        $reponse = $this->getJson('/api/v1/desktop/session')->assertOk();
        $token = $reponse->json('data.token');

        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    // --------------------------------------------------------------- sync:pull

    public function test_sync_pull_cree_une_ligne_absente_localement(): void
    {
        $ecole = School::create(['name' => 'X', 'code' => 'X', 'type' => 'secondaire', 'is_active' => true]);
        $this->provisionnerSansHttp($ecole);

        Http::fake(['*/api/v1/sync*' => Http::response($this->reponseSyncAvecUnEleve(
            id: 601, nom: 'NOUVEL ELEVE', updatedAt: now(), schoolId: $ecole->id,
        ), 200)]);

        Artisan::call('sync:pull');

        $this->assertDatabaseHas('eleves', ['id' => 601, 'nom_complet' => 'NOUVEL ELEVE']);
    }

    /** Une ligne locale plus récente que celle reçue n'est pas écrasée : elle n'a pas encore été poussée. */
    public function test_sync_pull_garde_la_ligne_locale_si_elle_est_plus_recente(): void
    {
        $ecole = School::create(['name' => 'X', 'code' => 'X', 'type' => 'secondaire', 'is_active' => true]);
        $this->provisionnerSansHttp($ecole);
        $this->creerEleveAvecId(602, $ecole->id, 'VERSION LOCALE');

        Http::fake(['*/api/v1/sync*' => Http::response($this->reponseSyncAvecUnEleve(
            id: 602, nom: 'VERSION DISTANTE PERIMEE', updatedAt: now()->subDays(2), schoolId: $ecole->id,
        ), 200)]);

        Artisan::call('sync:pull');

        $this->assertDatabaseHas('eleves', ['id' => 602, 'nom_complet' => 'VERSION LOCALE']);
    }

    /** À l'inverse, une ligne distante plus récente écrase la version locale obsolète. */
    public function test_sync_pull_ecrase_la_ligne_locale_si_elle_est_plus_ancienne(): void
    {
        $ecole = School::create(['name' => 'X', 'code' => 'X', 'type' => 'secondaire', 'is_active' => true]);
        $this->provisionnerSansHttp($ecole);
        $this->creerEleveAvecId(603, $ecole->id, 'VERSION PERIMEE');
        \DB::table('eleves')->where('id', 603)->update(['updated_at' => now()->subDays(5)]);

        Http::fake(['*/api/v1/sync*' => Http::response($this->reponseSyncAvecUnEleve(
            id: 603, nom: 'VERSION A JOUR', updatedAt: now(), schoolId: $ecole->id,
        ), 200)]);

        Artisan::call('sync:pull');

        $this->assertDatabaseHas('eleves', ['id' => 603, 'nom_complet' => 'VERSION A JOUR']);
    }

    // --------------------------------------------------------------- sync:push

    public function test_sync_push_marque_les_operations_reussies(): void
    {
        $this->provisionnerSansHttp();

        SyncOutbox::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'methode' => 'POST', 'chemin' => 'annonces', 'corps' => ['titre' => 'Test']]);
        $enAttente = SyncOutbox::query()->enAttente()->first();

        Http::fake(['*/api/v1/sync*' => Http::response([
            'success' => true,
            'data' => ['resultats' => [['id' => $enAttente->id, 'statut' => 201, 'reponse' => []]]],
        ], 200)]);

        Artisan::call('sync:push');

        $this->assertNotNull($enAttente->fresh()->pushed_at);
    }

    public function test_sync_push_garde_dans_loutbox_une_operation_refusee(): void
    {
        $this->provisionnerSansHttp();

        SyncOutbox::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'methode' => 'POST', 'chemin' => 'annonces', 'corps' => []]);
        $enAttente = SyncOutbox::query()->enAttente()->first();

        Http::fake(['*/api/v1/sync*' => Http::response([
            'success' => true,
            'data' => ['resultats' => [['id' => $enAttente->id, 'statut' => 422, 'reponse' => []]]],
        ], 200)]);

        Artisan::call('sync:push');

        $frais = $enAttente->fresh();
        $this->assertNull($frais->pushed_at);
        $this->assertSame(1, $frais->tentatives);
    }

    // ----------------------------------------------------------- Middleware

    public function test_le_middleware_outbox_enregistre_une_ecriture_reussie_en_mode_local(): void
    {
        config(['sync.local_replica' => true]);

        $ecole = School::create(['name' => 'X', 'code' => 'X', 'type' => 'secondaire', 'is_active' => true]);
        $user = User::factory()->create(['school_id' => $ecole->id]);
        $user->assignRole('super_admin');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/appareils', ['jeton' => 'abc123', 'plateforme' => 'android'])
            ->assertOk();

        $this->assertDatabaseHas('sync_outbox', ['methode' => 'POST', 'chemin' => 'appareils']);
    }

    public function test_le_middleware_outbox_ignore_les_routes_de_sync_et_auth(): void
    {
        config(['sync.local_replica' => true]);

        $ecole = School::create(['name' => 'X', 'code' => 'X', 'type' => 'secondaire', 'is_active' => true]);
        $user = User::factory()->create(['school_id' => $ecole->id]);
        $user->assignRole('super_admin');

        Http::fake(['*/api/v1/sync*' => Http::response(['success' => true, 'data' => ['resultats' => []]], 200)]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/sync', ['operations' => [
            ['id' => 'op-1', 'methode' => 'POST', 'chemin' => 'appareils', 'corps' => []],
        ]])->assertOk();

        $this->assertDatabaseCount('sync_outbox', 0);
    }

    public function test_le_middleware_outbox_est_inactif_sans_le_mode_local(): void
    {
        // `sync.local_replica` reste faux par défaut : comportement du serveur distant.
        $ecole = School::create(['name' => 'X', 'code' => 'X', 'type' => 'secondaire', 'is_active' => true]);
        $user = User::factory()->create(['school_id' => $ecole->id]);
        $user->assignRole('super_admin');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/appareils', ['jeton' => 'def456', 'plateforme' => 'ios'])
            ->assertOk();

        $this->assertDatabaseCount('sync_outbox', 0);
    }

    // ------------------------------------------------------------------ aides

    /** `id` n'étant fillable sur aucun modèle, `create(['id' => ...])` l'ignorerait silencieusement. */
    private function creerEleveAvecId(int $id, int $schoolId, string $nom): Eleve
    {
        $eleve = new Eleve();
        $eleve->id = $id;
        $eleve->fill([
            'school_id' => $schoolId, 'matricule' => "M-{$id}",
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $eleve->save();

        return $eleve;
    }

    private function provisionnerSansHttp(?School $ecole = null): DesktopProvisioning
    {
        $user = User::factory()->create();

        $provisioning = DesktopProvisioning::create([
            'user_id' => $user->id,
            'serveur_url' => 'https://distant.test',
            'token' => 'jeton-acces',
            'refresh_token' => 'jeton-refresh',
            'provisionne_le' => now(),
        ]);

        if ($ecole !== null) {
            DesktopProvisioningEcole::create(['desktop_provisioning_id' => $provisioning->id, 'school_id' => $ecole->id]);
        }

        return $provisioning;
    }

    /** @return array<string, mixed> */
    private function reponseSyncAvecUnEleve(int $id, string $nom, \DateTimeInterface $updatedAt, int $schoolId = 1): array
    {
        return [
            'success' => true,
            'data' => [
                'curseur' => now()->toIso8601ZuluString(),
                'complet' => true,
                'donnees' => [
                    'eleves' => [[
                        'id' => $id,
                        'school_id' => $schoolId,
                        'classe_id' => null,
                        'matricule' => "M-{$id}",
                        'nom_complet' => $nom,
                        'sexe' => 'M',
                        'statut' => 'actif',
                        'updated_at' => $updatedAt instanceof \Illuminate\Support\Carbon
                            ? $updatedAt->toIso8601ZuluString()
                            : \Illuminate\Support\Carbon::instance($updatedAt)->toIso8601ZuluString(),
                    ]],
                ],
                'suppressions' => [],
            ],
        ];
    }
}
