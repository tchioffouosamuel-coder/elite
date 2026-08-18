<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: champs_personnalises
 * Lignes: 1
 */
class ChampsPersonnalisesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'champs_personnalises';

        $rows = [
            ['id' => 1, 'classe_matiere_id' => 139, 'libelle' => 'Projet de groupe', 'type' => 'texte', 'ordre' => 1, 'created_at' => '2026-08-17 01:38:13', 'updated_at' => '2026-08-17 01:38:13'],
        ];
        DB::table($table)->insert($rows);

    }
}
