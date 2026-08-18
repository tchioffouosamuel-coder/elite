<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: trimestres
 * Lignes: 9
 */
class TrimestresSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'trimestres';

        $rows = [
            ['id' => 1, 'annee_scolaire_id' => 3, 'libelle' => 'Trimestre 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => 1, 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
            ['id' => 2, 'annee_scolaire_id' => 3, 'libelle' => 'Trimestre 2', 'ordre' => 2, 'date_debut' => '2027-01-05', 'date_fin' => '2027-03-27', 'is_active' => 0, 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
            ['id' => 3, 'annee_scolaire_id' => 3, 'libelle' => 'Trimestre 3', 'ordre' => 3, 'date_debut' => '2027-04-06', 'date_fin' => '2027-06-30', 'is_active' => 0, 'created_at' => '2026-08-10 19:27:56', 'updated_at' => '2026-08-10 19:27:56'],
            ['id' => 4, 'annee_scolaire_id' => 2, 'libelle' => 'Trimestre 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => 1, 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 5, 'annee_scolaire_id' => 2, 'libelle' => 'Trimestre 2', 'ordre' => 2, 'date_debut' => '2027-01-05', 'date_fin' => '2027-03-27', 'is_active' => 0, 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 6, 'annee_scolaire_id' => 2, 'libelle' => 'Trimestre 3', 'ordre' => 3, 'date_debut' => '2027-04-06', 'date_fin' => '2027-06-30', 'is_active' => 0, 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 7, 'annee_scolaire_id' => 1, 'libelle' => 'Trimestre 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => 1, 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 8, 'annee_scolaire_id' => 1, 'libelle' => 'Trimestre 2', 'ordre' => 2, 'date_debut' => '2027-01-05', 'date_fin' => '2027-03-27', 'is_active' => 0, 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 9, 'annee_scolaire_id' => 1, 'libelle' => 'Trimestre 3', 'ordre' => 3, 'date_debut' => '2027-04-06', 'date_fin' => '2027-06-30', 'is_active' => 0, 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
        ];
        DB::table($table)->insert($rows);

    }
}
