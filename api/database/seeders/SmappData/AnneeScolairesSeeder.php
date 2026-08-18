<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: annee_scolaires
 * Lignes: 3
 */
class AnneeScolairesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'annee_scolaires';

        $rows = [
            ['id' => 1, 'school_id' => 2, 'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'is_active' => 1, 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-10 19:27:54'],
            ['id' => 2, 'school_id' => 3, 'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'is_active' => 1, 'created_at' => '2026-08-10 19:27:55', 'updated_at' => '2026-08-10 19:27:55'],
            ['id' => 3, 'school_id' => 1, 'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'is_active' => 1, 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
        ];
        DB::table($table)->insert($rows);

    }
}
