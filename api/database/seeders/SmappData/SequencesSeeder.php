<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: sequences
 * Lignes: 24
 */
class SequencesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'sequences';

        $rows = [
            ['id' => 1, 'trimestre_id' => 1, 'ordre' => 1, 'libelle' => 'Séquence 1', 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
            ['id' => 2, 'trimestre_id' => 1, 'ordre' => 2, 'libelle' => 'Séquence 2', 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
            ['id' => 3, 'trimestre_id' => 2, 'ordre' => 1, 'libelle' => 'Séquence 1', 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
            ['id' => 4, 'trimestre_id' => 2, 'ordre' => 2, 'libelle' => 'Séquence 2', 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
            ['id' => 5, 'trimestre_id' => 3, 'ordre' => 1, 'libelle' => 'Séquence 1', 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
            ['id' => 6, 'trimestre_id' => 3, 'ordre' => 2, 'libelle' => 'Séquence 2', 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
            ['id' => 7, 'trimestre_id' => 4, 'ordre' => 1, 'libelle' => 'Séquence 1', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 8, 'trimestre_id' => 4, 'ordre' => 2, 'libelle' => 'Séquence 2', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 9, 'trimestre_id' => 4, 'ordre' => 3, 'libelle' => 'Séquence 3', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 10, 'trimestre_id' => 5, 'ordre' => 1, 'libelle' => 'Séquence 1', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 11, 'trimestre_id' => 5, 'ordre' => 2, 'libelle' => 'Séquence 2', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 12, 'trimestre_id' => 5, 'ordre' => 3, 'libelle' => 'Séquence 3', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 13, 'trimestre_id' => 6, 'ordre' => 1, 'libelle' => 'Séquence 1', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 14, 'trimestre_id' => 6, 'ordre' => 2, 'libelle' => 'Séquence 2', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 15, 'trimestre_id' => 6, 'ordre' => 3, 'libelle' => 'Séquence 3', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 16, 'trimestre_id' => 7, 'ordre' => 1, 'libelle' => 'Séquence 1', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 17, 'trimestre_id' => 7, 'ordre' => 2, 'libelle' => 'Séquence 2', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 18, 'trimestre_id' => 7, 'ordre' => 3, 'libelle' => 'Séquence 3', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 19, 'trimestre_id' => 8, 'ordre' => 1, 'libelle' => 'Séquence 1', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 20, 'trimestre_id' => 8, 'ordre' => 2, 'libelle' => 'Séquence 2', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 21, 'trimestre_id' => 8, 'ordre' => 3, 'libelle' => 'Séquence 3', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 22, 'trimestre_id' => 9, 'ordre' => 1, 'libelle' => 'Séquence 1', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 23, 'trimestre_id' => 9, 'ordre' => 2, 'libelle' => 'Séquence 2', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 24, 'trimestre_id' => 9, 'ordre' => 3, 'libelle' => 'Séquence 3', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
        ];
        DB::table($table)->insert($rows);

    }
}
