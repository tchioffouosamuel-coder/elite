<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: grilles_frais
 * Lignes: 1
 */
class GrillesFraisSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'grilles_frais';

        $rows = [
            ['id' => 9, 'school_id' => 2, 'annee_scolaire_id' => 1, 'classe_id' => null, 'montant' => 90000, 'created_at' => '2026-08-16 16:42:13', 'updated_at' => '2026-08-16 16:42:13'],
        ];
        DB::table($table)->insert($rows);

    }
}
