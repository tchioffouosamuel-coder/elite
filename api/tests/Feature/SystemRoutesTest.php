<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_des_routes_est_reservee_au_super_admin(): void
    {
        $user = User::create([
            'name' => 'Utilisateur',
            'email' => 'utilisateur@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/system/routes')
            ->assertForbidden();
    }

    public function test_la_liste_des_routes_est_renvoyee_en_json(): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/system/routes')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', fn (int $count) => $count > 0);

        $this->assertSame(
            $response->json('data.count'),
            count($response->json('data.routes')),
        );
        $this->assertTrue(collect($response->json('data.routes'))->contains(
            fn (array $route) => $route['uri'] === 'api/v1/system/routes',
        ));
    }
}