<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: classe_matieres
 * Lignes: 31
 */
class ClasseMatieresSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'classe_matieres';

        $rows = [
            ['id' => 101, 'classe_id' => 195, 'matiere_id' => 134, 'personnel_id' => null, 'coefficient' => 1.0, 'quota_horaire' => 15, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-15 18:21:03', 'updated_at' => '2026-08-16 08:34:06'],
            ['id' => 102, 'classe_id' => 195, 'matiere_id' => 137, 'personnel_id' => null, 'coefficient' => 1.0, 'quota_horaire' => 20, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-15 18:22:03', 'updated_at' => '2026-08-16 08:34:06'],
            ['id' => 103, 'classe_id' => 206, 'matiere_id' => 123, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:23:16', 'updated_at' => '2026-08-16 10:23:16'],
            ['id' => 107, 'classe_id' => 206, 'matiere_id' => 130, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:20', 'updated_at' => '2026-08-16 10:33:20'],
            ['id' => 109, 'classe_id' => 206, 'matiere_id' => 141, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:21', 'updated_at' => '2026-08-16 10:33:21'],
            ['id' => 113, 'classe_id' => 206, 'matiere_id' => 133, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:23', 'updated_at' => '2026-08-16 10:33:23'],
            ['id' => 114, 'classe_id' => 206, 'matiere_id' => 140, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:24', 'updated_at' => '2026-08-16 10:33:24'],
            ['id' => 115, 'classe_id' => 206, 'matiere_id' => 121, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:25', 'updated_at' => '2026-08-16 10:33:25'],
            ['id' => 116, 'classe_id' => 206, 'matiere_id' => 138, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:25', 'updated_at' => '2026-08-16 10:33:25'],
            ['id' => 117, 'classe_id' => 206, 'matiere_id' => 122, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:26', 'updated_at' => '2026-08-16 10:33:26'],
            ['id' => 118, 'classe_id' => 206, 'matiere_id' => 128, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:27', 'updated_at' => '2026-08-16 10:33:27'],
            ['id' => 119, 'classe_id' => 206, 'matiere_id' => 139, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:28', 'updated_at' => '2026-08-16 10:33:28'],
            ['id' => 121, 'classe_id' => 206, 'matiere_id' => 132, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:30', 'updated_at' => '2026-08-16 10:33:30'],
            ['id' => 122, 'classe_id' => 206, 'matiere_id' => 129, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:30', 'updated_at' => '2026-08-16 10:33:30'],
            ['id' => 123, 'classe_id' => 206, 'matiere_id' => 126, 'personnel_id' => 279, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 10:33:31', 'updated_at' => '2026-08-16 10:33:31'],
            ['id' => 125, 'classe_id' => 201, 'matiere_id' => 134, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:24', 'updated_at' => '2026-08-16 20:21:24'],
            ['id' => 126, 'classe_id' => 201, 'matiere_id' => 135, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:25', 'updated_at' => '2026-08-16 20:21:25'],
            ['id' => 127, 'classe_id' => 201, 'matiere_id' => 130, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:25', 'updated_at' => '2026-08-16 20:21:25'],
            ['id' => 128, 'classe_id' => 201, 'matiere_id' => 131, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:25', 'updated_at' => '2026-08-16 20:21:25'],
            ['id' => 129, 'classe_id' => 201, 'matiere_id' => 127, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:26', 'updated_at' => '2026-08-16 20:21:26'],
            ['id' => 130, 'classe_id' => 201, 'matiere_id' => 125, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:26', 'updated_at' => '2026-08-16 20:21:26'],
            ['id' => 131, 'classe_id' => 201, 'matiere_id' => 133, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:27', 'updated_at' => '2026-08-16 20:21:27'],
            ['id' => 132, 'classe_id' => 201, 'matiere_id' => 140, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:27', 'updated_at' => '2026-08-16 20:21:27'],
            ['id' => 133, 'classe_id' => 201, 'matiere_id' => 138, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:27', 'updated_at' => '2026-08-16 20:21:27'],
            ['id' => 134, 'classe_id' => 201, 'matiere_id' => 122, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:28', 'updated_at' => '2026-08-16 20:21:28'],
            ['id' => 135, 'classe_id' => 201, 'matiere_id' => 139, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:28', 'updated_at' => '2026-08-16 20:21:28'],
            ['id' => 136, 'classe_id' => 201, 'matiere_id' => 124, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:28', 'updated_at' => '2026-08-16 20:21:28'],
            ['id' => 137, 'classe_id' => 201, 'matiere_id' => 129, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:29', 'updated_at' => '2026-08-16 20:21:29'],
            ['id' => 138, 'classe_id' => 201, 'matiere_id' => 126, 'personnel_id' => 276, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-16 20:21:29', 'updated_at' => '2026-08-16 20:21:29'],
            ['id' => 139, 'classe_id' => 139, 'matiere_id' => 25, 'personnel_id' => null, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-17 01:33:29', 'updated_at' => '2026-08-17 08:07:14'],
            ['id' => 140, 'classe_id' => 128, 'matiere_id' => 188, 'personnel_id' => 346, 'coefficient' => 1.0, 'quota_horaire' => null, 'groupe' => 1, 'competences' => null, 'statut' => 'actif', 'created_at' => '2026-08-17 02:52:55', 'updated_at' => '2026-08-17 02:52:55'],
        ];
        DB::table($table)->insert($rows);

    }
}
