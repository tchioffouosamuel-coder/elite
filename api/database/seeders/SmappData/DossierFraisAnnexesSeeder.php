<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: dossier_frais_annexes
 * Lignes: 2
 */
class DossierFraisAnnexesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'dossier_frais_annexes';

        $rows = [
            ['id' => 14, 'dossier_scolarite_id' => 25, 'frais_annexe_id' => 7, 'libelle' => 'Tenue de sport', 'montant' => 15000, 'created_at' => '2026-08-16 18:56:22', 'updated_at' => '2026-08-16 18:56:22'],
            ['id' => 15, 'dossier_scolarite_id' => 26, 'frais_annexe_id' => 7, 'libelle' => 'Tenue de sport', 'montant' => 15000, 'created_at' => '2026-08-16 18:59:27', 'updated_at' => '2026-08-16 18:59:27'],
        ];
        DB::table($table)->insert($rows);

    }
}
