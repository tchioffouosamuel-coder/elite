<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: complexes
 * Lignes: 1
 */
class ComplexesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'complexes';

        $rows = [
            ['id' => 1, 'name' => 'Complexe Scolaire ELITES', 'code' => 'ELITES', 'logo_path' => null, 'address' => 'Bertoua-Monou2, Cameroun', 'phone' => '698256973', 'email' => null, 'is_active' => 1, 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-10 19:27:54'],
        ];
        DB::table($table)->insert($rows);

    }
}
