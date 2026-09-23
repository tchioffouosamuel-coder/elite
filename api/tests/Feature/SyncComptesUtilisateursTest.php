<?php

namespace Tests\Feature;

use App\Models\DesktopProvisioning;
use App\Models\DesktopProvisioningEcole;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Entité `utilisateurs` du registre de synchronisation : l'écran « Comptes
 * utilisateurs » du super administrateur doit, sur le poste desktop, lister
 * tous les comptes et non le seul compte lié au poste.
 */
class SyncComptesUtilisateursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);
    }

    public function test_le_super_admin_recoit_les_comptes_de_lecole_sans_leurs_identifiants(): void
    {
        $ecole = $this->ecole('A');
        $autre = $this->ecole('B');

        $admin = User::factory()->create(['school_id' => $ecole->id]);
        $admin->assignRole('super_admin');

        $agent = User::factory()->create(['school_id' => $ecole->id, 'name' => 'Agent A']);
        $agent->assignRole('enseignant');
        $agent->schools()->attach($autre->id);

        $horsEcole = User::factory()->create(['school_id' => $autre->id]);

        Sanctum::actingAs($admin);
        $donnees = $this->withHeader('X-School-Id', (string) $ecole->id)
            ->getJson('/api/v1/sync?entites=utilisateurs')
            ->assertOk()
            ->json('data.donnees.utilisateurs');

        $parId = collect($donnees)->keyBy('id');

        $this->assertTrue($parId->has($admin->id));
        $this->assertTrue($parId->has($agent->id));
        $this->assertFalse($parId->has($horsEcole->id));

        $this->assertSame(['enseignant'], $parId[$agent->id]['roles']);
        $this->assertSame([$autre->id], $parId[$agent->id]['ecoles']);

        foreach ($donnees as $ligne) {
            $this->assertArrayNotHasKey('password', $ligne);
            $this->assertArrayNotHasKey('remember_token', $ligne);
            $this->assertArrayNotHasKey('otp_code', $ligne);
        }
    }

    public function test_un_compte_non_super_admin_ne_recoit_aucun_compte(): void
    {
        $ecole = $this->ecole('A');

        $directeur = User::factory()->create(['school_id' => $ecole->id]);
        $directeur->givePermissionTo(CataloguePermissions::codes());

        Sanctum::actingAs($directeur);
        $this->withHeader('X-School-Id', (string) $ecole->id)
            ->getJson('/api/v1/sync?entites=utilisateurs')
            ->assertOk()
            ->assertJsonMissingPath('data.donnees.utilisateurs');
    }

    public function test_sync_pull_cree_les_comptes_avec_roles_et_ecoles_sans_toucher_au_compte_du_poste(): void
    {
        $ecole = $this->ecole('A');
        $autre = $this->ecole('B');

        $compteDuPoste = User::factory()->create(['school_id' => $ecole->id]);
        $compteDuPoste->assignRole('super_admin');
        $provisioning = DesktopProvisioning::create([
            'user_id' => $compteDuPoste->id,
            'password' => bcrypt('x'),
            'serveur_url' => 'https://distant.test',
            'token' => 'jeton-acces',
            'refresh_token' => 'jeton-refresh',
            'provisionne_le' => now(),
        ]);
        DesktopProvisioningEcole::create(['desktop_provisioning_id' => $provisioning->id, 'school_id' => $ecole->id]);

        $maintenant = now()->addMinute()->toIso8601ZuluString();

        Http::fake(['*/api/v1/sync*' => Http::response([
            'success' => true,
            'data' => [
                'curseur' => now()->toIso8601ZuluString(),
                'complet' => true,
                'donnees' => [
                    'utilisateurs' => [
                        [
                            'id' => 9001, 'school_id' => $ecole->id, 'niveau_id' => null,
                            'name' => 'Agent distant', 'email' => 'agent@distant.test', 'phone' => null,
                            'locale' => 'fr', 'is_active' => true, 'doit_changer_mot_de_passe' => true,
                            'roles' => ['enseignant'], 'ecoles' => [$autre->id],
                            'updated_at' => $maintenant,
                        ],
                        [
                            // Le serveur exige un renouvellement et ne lui
                            // connaît qu'un rôle : le poste doit garder ses
                            // propres rôles et ne jamais bloquer la session.
                            'id' => $compteDuPoste->id, 'school_id' => $ecole->id, 'niveau_id' => null,
                            'name' => 'Nom mis à jour', 'email' => $compteDuPoste->email, 'phone' => null,
                            'locale' => 'fr', 'is_active' => true, 'doit_changer_mot_de_passe' => true,
                            'roles' => ['enseignant'], 'ecoles' => [],
                            'updated_at' => $maintenant,
                        ],
                    ],
                ],
                'suppressions' => [],
            ],
        ], 200)]);

        Artisan::call('sync:pull');

        $agent = User::find(9001);
        $this->assertNotNull($agent);
        $this->assertSame('Agent distant', $agent->name);
        $this->assertTrue($agent->hasRole('enseignant'));
        $this->assertSame([$autre->id], $agent->schools()->pluck('schools.id')->all());
        $this->assertNotEmpty($agent->password);

        $compteDuPoste->refresh();
        $this->assertSame('Nom mis à jour', $compteDuPoste->name);
        $this->assertTrue($compteDuPoste->hasRole('super_admin'));
        $this->assertFalse($compteDuPoste->hasRole('enseignant'));
        $this->assertFalse($compteDuPoste->doit_changer_mot_de_passe);
    }

    private function ecole(string $code): School
    {
        return School::create(['name' => "École {$code}", 'code' => $code, 'type' => 'secondaire', 'is_active' => true]);
    }
}
