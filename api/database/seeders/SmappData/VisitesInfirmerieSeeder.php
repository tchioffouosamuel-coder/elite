<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: visites_infirmerie
 * Lignes: 1
 */
class VisitesInfirmerieSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'visites_infirmerie';

        $rows = [
            ['id' => 1, 'eleve_id' => 3782, 'classe_id' => 201, 'date_visite' => '2026-08-17 04:03:00', 'raison' => 'Maux de tête et nausées', 'soins_prodiges' => 'Paracétamol 500mg x 10', 'cout_soins' => 100, 'observations' => null, 'enregistre_par' => null, 'created_at' => '2026-08-17 02:06:24', 'updated_at' => '2026-08-17 02:06:24'],
        ];
        DB::table($table)->insert($rows);

    }
}
