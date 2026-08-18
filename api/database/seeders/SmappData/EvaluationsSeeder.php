<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: evaluations
 * Lignes: 1
 */
class EvaluationsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'evaluations';

        $rows = [
            ['id' => 1, 'school_id' => 1, 'classe_matiere_id' => 139, 'progression_item_id' => null, 'titre' => 'Interro sur les acides et bases', 'type' => 'interrogation', 'date_prevue' => null, 'bareme' => 20, 'competences' => null, 'cree_par' => null, 'created_at' => '2026-08-17 01:38:49', 'updated_at' => '2026-08-17 01:38:49'],
        ];
        DB::table($table)->insert($rows);

    }
}
