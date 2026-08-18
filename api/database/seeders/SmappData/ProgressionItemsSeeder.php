<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: progression_items
 * Lignes: 1
 */
class ProgressionItemsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'progression_items';

        $rows = [
            ['id' => 25, 'classe_matiere_id' => 139, 'parent_id' => null, 'type' => 'lecon', 'titre' => 'Les acides et les bases', 'description' => null, 'objectifs' => 'Identifier un acide et une base', 'materiel' => 'Papier pH, béchers, indicateurs colorés', 'activites' => 'Manipulation en groupe puis synthèse collective', 'devoirs' => 'Exercices 3 et 4 page 42', 'ordre' => 1, 'sequence_id' => null, 'duree_prevue' => null, 'created_at' => '2026-08-17 01:35:28', 'updated_at' => '2026-08-17 01:35:28'],
        ];
        DB::table($table)->insert($rows);

    }
}
