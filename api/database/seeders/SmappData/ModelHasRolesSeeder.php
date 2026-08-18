<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: model_has_roles
 * Lignes: 15
 */
class ModelHasRolesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'model_has_roles';

        $rows = [
            ['role_id' => 1, 'model_type' => 'App\\Models\\User', 'model_id' => 1],
            ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 2],
            ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 3],
            ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 4],
            ['role_id' => 3, 'model_type' => 'App\\Models\\User', 'model_id' => 5],
            ['role_id' => 4, 'model_type' => 'App\\Models\\User', 'model_id' => 6],
            ['role_id' => 4, 'model_type' => 'App\\Models\\User', 'model_id' => 7],
            ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 8],
            ['role_id' => 8, 'model_type' => 'App\\Models\\User', 'model_id' => 9],
            ['role_id' => 4, 'model_type' => 'App\\Models\\User', 'model_id' => 10],
            ['role_id' => 4, 'model_type' => 'App\\Models\\User', 'model_id' => 11],
            ['role_id' => 4, 'model_type' => 'App\\Models\\User', 'model_id' => 12],
            ['role_id' => 4, 'model_type' => 'App\\Models\\User', 'model_id' => 13],
            ['role_id' => 4, 'model_type' => 'App\\Models\\User', 'model_id' => 14],
            ['role_id' => 4, 'model_type' => 'App\\Models\\User', 'model_id' => 15],
        ];
        DB::table($table)->insert($rows);

    }
}
