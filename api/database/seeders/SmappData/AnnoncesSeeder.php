<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: annonces
 * Lignes: 1
 */
class AnnoncesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'annonces';

        $rows = [
            ['id' => 2, 'school_id' => 1, 'titre' => 'Rentrée Scolaire', 'contenu' => 'Bonne rentrée scolaire à tous !!', 'publie_par' => null, 'publiee_le' => '2026-08-17 08:46:49', 'created_at' => '2026-08-17 07:46:49', 'updated_at' => '2026-08-17 07:46:49'],
        ];
        DB::table($table)->insert($rows);

    }
}
