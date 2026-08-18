<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: remunerations
 * Lignes: 13
 */
class RemunerationsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'remunerations';

        $rows = [
            ['id' => 13, 'school_id' => 2, 'personnel_id' => 273, 'date_effet' => '2026-08-16', 'salaire_base' => 50000, 'prime_anciennete' => 4, 'prime_communication' => 3000, 'prime_transport' => 1000, 'prime_recherche' => 2000, 'prime_performance' => 5000, 'categorie' => null, 'created_at' => '2026-08-16 19:13:52', 'updated_at' => '2026-08-16 19:13:52'],
            ['id' => 14, 'school_id' => 3, 'personnel_id' => 358, 'date_effet' => '2026-08-16', 'salaire_base' => 150000, 'prime_anciennete' => 0, 'prime_communication' => 0, 'prime_transport' => 0, 'prime_recherche' => 0, 'prime_performance' => 0, 'categorie' => null, 'created_at' => '2026-08-16 19:27:33', 'updated_at' => '2026-08-16 19:27:33'],
            ['id' => 15, 'school_id' => 3, 'personnel_id' => 353, 'date_effet' => '2026-08-16', 'salaire_base' => 150000, 'prime_anciennete' => 0, 'prime_communication' => 0, 'prime_transport' => 0, 'prime_recherche' => 0, 'prime_performance' => 0, 'categorie' => null, 'created_at' => '2026-08-16 19:28:57', 'updated_at' => '2026-08-16 19:28:57'],
            ['id' => 16, 'school_id' => 3, 'personnel_id' => 357, 'date_effet' => '2026-08-16', 'salaire_base' => 150000, 'prime_anciennete' => 0, 'prime_communication' => 0, 'prime_transport' => 0, 'prime_recherche' => 0, 'prime_performance' => 0, 'categorie' => null, 'created_at' => '2026-08-16 19:28:57', 'updated_at' => '2026-08-16 19:28:57'],
            ['id' => 17, 'school_id' => 2, 'personnel_id' => 279, 'date_effet' => '2026-08-16', 'salaire_base' => 50000, 'prime_anciennete' => 4, 'prime_communication' => 3000, 'prime_transport' => 1000, 'prime_recherche' => 2000, 'prime_performance' => 5000, 'categorie' => null, 'created_at' => '2026-08-16 19:35:55', 'updated_at' => '2026-08-16 19:35:55'],
            ['id' => 18, 'school_id' => 2, 'personnel_id' => 275, 'date_effet' => '2026-08-16', 'salaire_base' => 50000, 'prime_anciennete' => 4, 'prime_communication' => 3000, 'prime_transport' => 1000, 'prime_recherche' => 2000, 'prime_performance' => 5000, 'categorie' => null, 'created_at' => '2026-08-16 19:35:55', 'updated_at' => '2026-08-16 19:35:55'],
            ['id' => 19, 'school_id' => 2, 'personnel_id' => 278, 'date_effet' => '2026-08-16', 'salaire_base' => 50000, 'prime_anciennete' => 4, 'prime_communication' => 3000, 'prime_transport' => 1000, 'prime_recherche' => 2000, 'prime_performance' => 5000, 'categorie' => null, 'created_at' => '2026-08-16 19:35:55', 'updated_at' => '2026-08-16 19:35:55'],
            ['id' => 20, 'school_id' => 2, 'personnel_id' => 271, 'date_effet' => '2026-08-16', 'salaire_base' => 50000, 'prime_anciennete' => 4, 'prime_communication' => 3000, 'prime_transport' => 1000, 'prime_recherche' => 2000, 'prime_performance' => 5000, 'categorie' => null, 'created_at' => '2026-08-16 19:35:55', 'updated_at' => '2026-08-16 19:35:55'],
            ['id' => 21, 'school_id' => 2, 'personnel_id' => 272, 'date_effet' => '2026-08-16', 'salaire_base' => 50000, 'prime_anciennete' => 4, 'prime_communication' => 3000, 'prime_transport' => 1000, 'prime_recherche' => 2000, 'prime_performance' => 5000, 'categorie' => null, 'created_at' => '2026-08-16 19:35:55', 'updated_at' => '2026-08-16 19:35:55'],
            ['id' => 22, 'school_id' => 2, 'personnel_id' => 276, 'date_effet' => '2026-08-16', 'salaire_base' => 50000, 'prime_anciennete' => 4, 'prime_communication' => 3000, 'prime_transport' => 1000, 'prime_recherche' => 2000, 'prime_performance' => 5000, 'categorie' => null, 'created_at' => '2026-08-16 19:35:55', 'updated_at' => '2026-08-16 19:35:55'],
            ['id' => 23, 'school_id' => 2, 'personnel_id' => 274, 'date_effet' => '2026-08-16', 'salaire_base' => 50000, 'prime_anciennete' => 4, 'prime_communication' => 3000, 'prime_transport' => 1000, 'prime_recherche' => 2000, 'prime_performance' => 5000, 'categorie' => null, 'created_at' => '2026-08-16 19:35:55', 'updated_at' => '2026-08-16 19:35:55'],
            ['id' => 24, 'school_id' => 2, 'personnel_id' => 277, 'date_effet' => '2026-08-16', 'salaire_base' => 50000, 'prime_anciennete' => 4, 'prime_communication' => 3000, 'prime_transport' => 1000, 'prime_recherche' => 2000, 'prime_performance' => 5000, 'categorie' => null, 'created_at' => '2026-08-16 19:35:55', 'updated_at' => '2026-08-16 19:35:55'],
            ['id' => 25, 'school_id' => 2, 'personnel_id' => 280, 'date_effet' => '2026-08-16', 'salaire_base' => 50000, 'prime_anciennete' => 4, 'prime_communication' => 3000, 'prime_transport' => 1000, 'prime_recherche' => 2000, 'prime_performance' => 5000, 'categorie' => null, 'created_at' => '2026-08-16 19:35:55', 'updated_at' => '2026-08-16 19:35:55'],
        ];
        DB::table($table)->insert($rows);

    }
}
