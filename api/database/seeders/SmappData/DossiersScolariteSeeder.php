<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: dossiers_scolarite
 * Lignes: 4
 */
class DossiersScolariteSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'dossiers_scolarite';

        $rows = [
            ['id' => 25, 'school_id' => 2, 'annee_scolaire_id' => 1, 'eleve_id' => 3633, 'montant_scolarite' => 90000, 'remise' => 0, 'report_dette' => 0, 'observation' => null, 'created_at' => '2026-08-16 18:56:22', 'updated_at' => '2026-08-16 18:56:22'],
            ['id' => 26, 'school_id' => 2, 'annee_scolaire_id' => 1, 'eleve_id' => 3676, 'montant_scolarite' => 90000, 'remise' => 0, 'report_dette' => 0, 'observation' => null, 'created_at' => '2026-08-16 18:59:27', 'updated_at' => '2026-08-16 18:59:27'],
            ['id' => 27, 'school_id' => 1, 'annee_scolaire_id' => 3, 'eleve_id' => 4573, 'montant_scolarite' => 0, 'remise' => 0, 'report_dette' => 0, 'observation' => null, 'created_at' => '2026-08-17 02:15:26', 'updated_at' => '2026-08-17 02:15:26'],
            ['id' => 28, 'school_id' => 3, 'annee_scolaire_id' => 2, 'eleve_id' => 3958, 'montant_scolarite' => 0, 'remise' => 0, 'report_dette' => 0, 'observation' => null, 'created_at' => '2026-08-17 03:25:43', 'updated_at' => '2026-08-17 03:25:43'],
        ];
        DB::table($table)->insert($rows);

    }
}
