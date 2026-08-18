<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: departements
 * Lignes: 3
 */
class DepartementsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'departements';

        $rows = [
            ['id' => 1, 'school_id' => 1, 'nom' => 'Sciences', 'head_personnel_id' => null, 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-16 08:28:32'],
            ['id' => 2, 'school_id' => 1, 'nom' => 'Lettres', 'head_personnel_id' => null, 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-16 08:28:32'],
            ['id' => 3, 'school_id' => 1, 'nom' => 'Administration', 'head_personnel_id' => null, 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
        ];
        DB::table($table)->insert($rows);

    }
}
