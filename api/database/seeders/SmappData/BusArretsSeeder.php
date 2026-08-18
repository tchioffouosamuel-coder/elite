<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: bus_arrets
 * Lignes: 4
 */
class BusArretsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'bus_arrets';

        $rows = [
            ['id' => 1, 'trajet_id' => 1, 'nom' => 'Carrefour Bastos', 'ordre' => 1, 'heure_passage' => '06:30:00', 'created_at' => '2026-08-17 01:24:34', 'updated_at' => '2026-08-17 01:24:34'],
            ['id' => 2, 'trajet_id' => 1, 'nom' => 'Marché Mokolo', 'ordre' => 2, 'heure_passage' => '06:45:00', 'created_at' => '2026-08-17 01:24:34', 'updated_at' => '2026-08-17 01:24:34'],
            ['id' => 11, 'trajet_id' => 11, 'nom' => 'Dabadji', 'ordre' => 1, 'heure_passage' => '08:00:00', 'created_at' => '2026-08-17 05:07:17', 'updated_at' => '2026-08-17 05:07:17'],
            ['id' => 12, 'trajet_id' => 11, 'nom' => 'Ngaikada', 'ordre' => 1, 'heure_passage' => '10:00:00', 'created_at' => '2026-08-17 05:07:42', 'updated_at' => '2026-08-17 05:07:42'],
        ];
        DB::table($table)->insert($rows);

    }
}
