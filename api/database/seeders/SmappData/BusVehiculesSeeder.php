<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: bus_vehicules
 * Lignes: 2
 */
class BusVehiculesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'bus_vehicules';

        $rows = [
            ['id' => 1, 'school_id' => 1, 'immatriculation' => 'CE-123-XY', 'marque' => 'Toyota Coaster', 'capacite' => 30, 'chauffeur_id' => null, 'statut' => 'actif', 'created_at' => '2026-08-17 01:24:34', 'updated_at' => '2026-08-17 01:24:34'],
            ['id' => 3, 'school_id' => 2, 'immatriculation' => 'LT490OJ', 'marque' => 'TOYOTA', 'capacite' => 30, 'chauffeur_id' => 271, 'statut' => 'actif', 'created_at' => '2026-08-17 02:07:25', 'updated_at' => '2026-08-17 02:07:25'],
        ];
        DB::table($table)->insert($rows);

    }
}
