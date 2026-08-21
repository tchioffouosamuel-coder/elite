<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: sous_systemes
 * Lignes: 9
 */
class SousSystemesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'sous_systemes';

        $rows = [
            ['id' => 1, 'school_id' => 1, 'code' => 'FR', 'nom' => 'Francophone', 'description' => 'Enseignement en français', 'created_at' => '2026-08-11 22:12:19', 'updated_at' => '2026-08-11 22:12:19'],
            ['id' => 2, 'school_id' => 1, 'code' => 'EN', 'nom' => 'Anglophone', 'description' => 'Enseignement en anglais', 'created_at' => '2026-08-11 22:12:19', 'updated_at' => '2026-08-11 22:12:19'],
            ['id' => 3, 'school_id' => 1, 'code' => 'BI', 'nom' => 'Bilingue', 'description' => 'Enseignement bilingue (français et anglais)', 'created_at' => '2026-08-11 22:12:19', 'updated_at' => '2026-08-11 22:12:19'],
            ['id' => 5, 'school_id' => 2, 'code' => 'FR', 'nom' => 'Francophone', 'description' => 'Enseignement en français', 'created_at' => '2026-08-11 22:13:21', 'updated_at' => '2026-08-11 22:13:21'],
            ['id' => 6, 'school_id' => 2, 'code' => 'EN', 'nom' => 'Anglophone', 'description' => 'Enseignement en anglais', 'created_at' => '2026-08-11 22:13:21', 'updated_at' => '2026-08-11 22:13:21'],
            ['id' => 7, 'school_id' => 2, 'code' => 'BI', 'nom' => 'Bilingue', 'description' => 'Enseignement bilingue (français et anglais)', 'created_at' => '2026-08-11 22:13:21', 'updated_at' => '2026-08-11 22:13:21'],
            ['id' => 8, 'school_id' => 3, 'code' => 'FR', 'nom' => 'Francophone', 'description' => 'Enseignement en français', 'created_at' => '2026-08-11 22:13:21', 'updated_at' => '2026-08-11 22:13:21'],
            ['id' => 9, 'school_id' => 3, 'code' => 'EN', 'nom' => 'Anglophone', 'description' => 'Enseignement en anglais', 'created_at' => '2026-08-11 22:13:21', 'updated_at' => '2026-08-11 22:13:21'],
            ['id' => 10, 'school_id' => 3, 'code' => 'BI', 'nom' => 'Bilingue', 'description' => 'Enseignement bilingue (français et anglais)', 'created_at' => '2026-08-11 22:13:21', 'updated_at' => '2026-08-11 22:13:21'],
        ];
        if (DB::table($table)->exists()) {
            return;
        }

        DB::table($table)->insert($rows);
    }
}
