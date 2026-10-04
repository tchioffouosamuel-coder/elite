<?php

namespace Tests\Feature;

use App\Models\DesktopProvisioning;
use App\Models\DesktopProvisioningEcole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les pauses et activités copiées sur un poste desktop avant que leur type
 * n'entre dans la synchronisation doivent être retéléchargées : la migration
 * remet à zéro le curseur de chaque école répliquée, et seulement sur un
 * poste desktop.
 */
class ReclonagePausesEmploiDuTempsTest extends TestCase
{
    use RefreshDatabase;

    private function ecoleProvisionnee(): DesktopProvisioningEcole
    {
        $school = School::create(['name' => 'Elites', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $user = User::create([
            'name' => 'Agent', 'email' => 'agent@test.local', 'password' => 'password',
            'school_id' => $school->id, 'is_active' => true,
        ]);
        $provisioning = DesktopProvisioning::create([
            'user_id' => $user->id, 'password' => bcrypt('x'), 'serveur_url' => 'https://distant.test',
            'token' => 't', 'refresh_token' => 'r', 'provisionne_le' => now(),
        ]);

        return DesktopProvisioningEcole::create([
            'desktop_provisioning_id' => $provisioning->id,
            'school_id' => $school->id,
            'curseur_sync' => '2026-10-01T08:00:00Z',
        ]);
    }

    private function executerMigration(): void
    {
        $migration = require database_path('migrations/2026_10_04_100000_reclonage_pour_pauses_emploi_du_temps.php');
        $migration->up();
    }

    public function test_sur_un_poste_desktop_le_curseur_est_remis_a_zero(): void
    {
        config(['sync.local_replica' => true]);
        $ecole = $this->ecoleProvisionnee();

        $this->executerMigration();

        $this->assertNull($ecole->fresh()->curseur_sync);
    }

    public function test_sur_le_serveur_central_rien_ne_change(): void
    {
        config(['sync.local_replica' => false]);
        $ecole = $this->ecoleProvisionnee();

        $this->executerMigration();

        $this->assertSame('2026-10-01T08:00:00Z', $ecole->fresh()->curseur_sync);
    }
}
