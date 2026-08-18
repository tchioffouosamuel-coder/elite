<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: versements
 * Lignes: 4
 */
class VersementsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'versements';

        $rows = [
            ['id' => 22, 'school_id' => 2, 'dossier_scolarite_id' => 25, 'numero_recu' => 'RC-ELITES-MAT-0001', 'date_versement' => '2026-08-16', 'montant' => 50000, 'mode' => 'especes', 'reference_externe' => null, 'encaisse_par' => 1, 'note' => null, 'annule_le' => null, 'annule_par' => null, 'motif_annulation' => null, 'created_at' => '2026-08-16 18:57:02', 'updated_at' => '2026-08-16 18:57:02'],
            ['id' => 23, 'school_id' => 1, 'dossier_scolarite_id' => 27, 'numero_recu' => 'RC-ELITES-BTA-0001', 'date_versement' => '2026-08-17', 'montant' => 25000, 'mode' => 'especes', 'reference_externe' => null, 'encaisse_par' => 1, 'note' => null, 'annule_le' => null, 'annule_par' => null, 'motif_annulation' => null, 'created_at' => '2026-08-17 02:15:54', 'updated_at' => '2026-08-17 02:15:54'],
            ['id' => 24, 'school_id' => 3, 'dossier_scolarite_id' => 28, 'numero_recu' => 'RC-ELITES-PRI-0001', 'date_versement' => '2026-08-17', 'montant' => 15000, 'mode' => 'especes', 'reference_externe' => null, 'encaisse_par' => 1, 'note' => null, 'annule_le' => null, 'annule_par' => null, 'motif_annulation' => null, 'created_at' => '2026-08-17 03:26:07', 'updated_at' => '2026-08-17 03:26:07'],
            ['id' => 29, 'school_id' => 2, 'dossier_scolarite_id' => 25, 'numero_recu' => 'RC-ELITES-MAT-0004', 'date_versement' => '2026-08-17', 'montant' => 9000, 'mode' => 'especes', 'reference_externe' => null, 'encaisse_par' => 1, 'note' => null, 'annule_le' => null, 'annule_par' => null, 'motif_annulation' => null, 'created_at' => '2026-08-17 05:31:34', 'updated_at' => '2026-08-17 05:31:34'],
        ];
        DB::table($table)->insert($rows);

    }
}
