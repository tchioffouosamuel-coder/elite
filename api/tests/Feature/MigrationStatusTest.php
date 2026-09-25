<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MigrationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_statut_des_migrations_est_reserve_au_super_admin(): void
    {
        $user = User::create([
            'name' => 'Utilisateur',
            'email' => 'utilisateur@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/system/migrations/status')
            ->assertForbidden();
    }

    public function test_le_statut_des_migrations_renvoie_la_sortie_de_la_commande(): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole($role);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/system/migrations/status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.exit_code', 0)
            ->assertJsonPath('data.output', fn (string $output) => str_contains($output, '0001_01_01_000000_create_users_table'));
    }
}