<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: roles
 * Lignes: 8
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'roles';

        $rows = [
            ['id' => 1, 'name' => 'super_admin', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-10 19:27:54'],
            ['id' => 2, 'name' => 'admin_etablissement', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-10 19:27:54'],
            ['id' => 3, 'name' => 'censeur_sg', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-10 19:27:54'],
            ['id' => 4, 'name' => 'enseignant', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-10 19:27:54'],
            ['id' => 5, 'name' => 'econome', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-10 19:27:54'],
            ['id' => 6, 'name' => 'parent', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-10 19:27:54'],
            ['id' => 7, 'name' => 'eleve', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-10 19:27:54'],
            ['id' => 8, 'name' => 'surveillant_general', 'guard_name' => 'web', 'created_at' => '2026-08-11 06:11:55', 'updated_at' => '2026-08-11 06:11:55'],
        ];
        DB::table($table)->insert($rows);

    }
}
