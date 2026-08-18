<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: bus_trajets
 * Lignes: 3
 */
class BusTrajetsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'bus_trajets';

        $rows = [
            ['id' => 1, 'school_id' => 1, 'vehicule_id' => 1, 'nom' => 'Trajet Nord', 'description' => null, 'tarif_aller_simple' => null, 'tarif_retour_simple' => null, 'tarif_aller_retour' => null, 'created_at' => '2026-08-17 01:24:34', 'updated_at' => '2026-08-17 01:24:34'],
            ['id' => 11, 'school_id' => 2, 'vehicule_id' => 3, 'nom' => 'EKOUNOU - BONIS', 'description' => null, 'tarif_aller_simple' => 5000, 'tarif_retour_simple' => 5000, 'tarif_aller_retour' => 9000, 'created_at' => '2026-08-17 02:07:53', 'updated_at' => '2026-08-17 03:34:24'],
            ['id' => 12, 'school_id' => 3, 'vehicule_id' => null, 'nom' => 'Trajet Sud', 'description' => null, 'tarif_aller_simple' => 8000, 'tarif_retour_simple' => 8000, 'tarif_aller_retour' => 15000, 'created_at' => '2026-08-17 03:15:38', 'updated_at' => '2026-08-17 03:15:38'],
        ];
        DB::table($table)->insert($rows);

    }
}
