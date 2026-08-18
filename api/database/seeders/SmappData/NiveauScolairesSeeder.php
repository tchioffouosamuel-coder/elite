<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: niveau_scolaires
 * Lignes: 6
 */
class NiveauScolairesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'niveau_scolaires';

        $rows = [
            ['id' => 1, 'school_id' => 3, 'code' => 'SIL', 'libelle' => 'Section d\'Initiation au Langage', 'ordre' => 1, 'animateur_personnel_id' => null, 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 2, 'school_id' => 3, 'code' => 'CP', 'libelle' => 'Cours Préparatoire', 'ordre' => 2, 'animateur_personnel_id' => null, 'created_at' => '2026-08-10 19:28:09', 'updated_at' => '2026-08-10 19:28:09'],
            ['id' => 3, 'school_id' => 3, 'code' => 'CE1', 'libelle' => 'Cours Élémentaire 1', 'ordre' => 3, 'animateur_personnel_id' => null, 'created_at' => '2026-08-10 19:28:18', 'updated_at' => '2026-08-10 19:28:18'],
            ['id' => 4, 'school_id' => 3, 'code' => 'CE2', 'libelle' => 'Cours Élémentaire 2', 'ordre' => 4, 'animateur_personnel_id' => null, 'created_at' => '2026-08-10 19:28:25', 'updated_at' => '2026-08-10 19:28:25'],
            ['id' => 5, 'school_id' => 3, 'code' => 'CM1', 'libelle' => 'Cours Moyen 1', 'ordre' => 5, 'animateur_personnel_id' => null, 'created_at' => '2026-08-10 19:28:32', 'updated_at' => '2026-08-10 19:28:32'],
            ['id' => 6, 'school_id' => 3, 'code' => 'CM2', 'libelle' => 'Cours Moyen 2', 'ordre' => 6, 'animateur_personnel_id' => null, 'created_at' => '2026-08-10 19:28:41', 'updated_at' => '2026-08-10 19:28:41'],
        ];
        DB::table($table)->insert($rows);

    }
}
