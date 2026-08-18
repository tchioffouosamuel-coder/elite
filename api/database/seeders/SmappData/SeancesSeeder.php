<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: seances
 * Lignes: 3
 */
class SeancesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'seances';

        $rows = [
            ['id' => 12, 'school_id' => 2, 'classe_id' => 201, 'classe_matiere_id' => 125, 'trimestre_id' => 7, 'emploi_du_temps_id' => null, 'date_seance' => '2026-08-17', 'heure_debut' => '08:00:00', 'heure_fin' => '09:00:00', 'salle' => null, 'contenu' => null, 'observations' => null, 'donnees_personnalisees' => null, 'statut' => 'effectuee', 'created_at' => '2026-08-17 02:15:58', 'updated_at' => '2026-08-17 02:48:53'],
            ['id' => 13, 'school_id' => 2, 'classe_id' => 201, 'classe_matiere_id' => 133, 'trimestre_id' => 7, 'emploi_du_temps_id' => null, 'date_seance' => '2026-08-17', 'heure_debut' => '08:00:00', 'heure_fin' => '09:00:00', 'salle' => null, 'contenu' => null, 'observations' => null, 'donnees_personnalisees' => null, 'statut' => 'effectuee', 'created_at' => '2026-08-17 03:18:00', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 16, 'school_id' => 2, 'classe_id' => 201, 'classe_matiere_id' => 126, 'trimestre_id' => 7, 'emploi_du_temps_id' => null, 'date_seance' => '2026-08-17', 'heure_debut' => '08:00:00', 'heure_fin' => '09:00:00', 'salle' => null, 'contenu' => null, 'observations' => null, 'donnees_personnalisees' => null, 'statut' => 'prevue', 'created_at' => '2026-08-17 05:20:43', 'updated_at' => '2026-08-17 05:20:43'],
        ];
        DB::table($table)->insert($rows);

    }
}
