<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: frais_annexes
 * Lignes: 1
 */
class FraisAnnexesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'frais_annexes';

        $rows = [
            ['id' => 7, 'school_id' => 2, 'annee_scolaire_id' => 1, 'libelle' => 'Tenue de sport', 'montant' => 15000, 'obligatoire' => 1, 'is_active' => 1, 'created_at' => '2026-08-16 18:55:11', 'updated_at' => '2026-08-16 18:55:25'],
        ];
        DB::table($table)->insert($rows);

    }
}
