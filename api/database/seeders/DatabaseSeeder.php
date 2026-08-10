<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            NiveauSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@elites-school.test'],
            ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true]
        );
        $superAdmin->assignRole('super_admin');

        $this->call(DemoDataSeeder::class);
    }
}
