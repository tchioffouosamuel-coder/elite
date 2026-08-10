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

        // DemoDataSeeder crée le complexe et ses trois écoles, puis alimente le
        // secondaire ; PrimaireMaternelleSeeder prend la suite sur les deux
        // autres cycles et dépend donc des écoles créées avant lui.
        $this->call([
            DemoDataSeeder::class,
            PrimaireMaternelleSeeder::class,
        ]);
    }
}
