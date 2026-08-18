<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: presences
 * Lignes: 66
 */
class PresencesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'presences';

        $rows = [
            ['id' => 21, 'seance_id' => 12, 'eleve_id' => 3771, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 22, 'seance_id' => 12, 'eleve_id' => 3770, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 23, 'seance_id' => 12, 'eleve_id' => 3762, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 24, 'seance_id' => 12, 'eleve_id' => 3782, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 25, 'seance_id' => 12, 'eleve_id' => 3772, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 26, 'seance_id' => 12, 'eleve_id' => 3778, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 27, 'seance_id' => 12, 'eleve_id' => 3777, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 28, 'seance_id' => 12, 'eleve_id' => 3760, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 29, 'seance_id' => 12, 'eleve_id' => 3789, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 30, 'seance_id' => 12, 'eleve_id' => 3786, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 31, 'seance_id' => 12, 'eleve_id' => 3774, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 32, 'seance_id' => 12, 'eleve_id' => 3764, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 33, 'seance_id' => 12, 'eleve_id' => 3779, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 34, 'seance_id' => 12, 'eleve_id' => 3791, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 35, 'seance_id' => 12, 'eleve_id' => 3761, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 36, 'seance_id' => 12, 'eleve_id' => 3780, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 37, 'seance_id' => 12, 'eleve_id' => 3773, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 38, 'seance_id' => 12, 'eleve_id' => 3792, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 39, 'seance_id' => 12, 'eleve_id' => 3769, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 40, 'seance_id' => 12, 'eleve_id' => 3768, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 41, 'seance_id' => 12, 'eleve_id' => 3784, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 42, 'seance_id' => 12, 'eleve_id' => 3788, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 43, 'seance_id' => 12, 'eleve_id' => 3787, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 44, 'seance_id' => 12, 'eleve_id' => 3785, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 45, 'seance_id' => 12, 'eleve_id' => 3783, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 46, 'seance_id' => 12, 'eleve_id' => 3765, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 47, 'seance_id' => 12, 'eleve_id' => 3766, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 48, 'seance_id' => 12, 'eleve_id' => 3790, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 49, 'seance_id' => 12, 'eleve_id' => 3763, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 50, 'seance_id' => 12, 'eleve_id' => 3776, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 51, 'seance_id' => 12, 'eleve_id' => 3781, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 52, 'seance_id' => 12, 'eleve_id' => 3767, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 53, 'seance_id' => 12, 'eleve_id' => 3775, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 02:48:53', 'updated_at' => '2026-08-17 02:49:13'],
            ['id' => 54, 'seance_id' => 13, 'eleve_id' => 3771, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 55, 'seance_id' => 13, 'eleve_id' => 3770, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 56, 'seance_id' => 13, 'eleve_id' => 3762, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 57, 'seance_id' => 13, 'eleve_id' => 3782, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 58, 'seance_id' => 13, 'eleve_id' => 3772, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 59, 'seance_id' => 13, 'eleve_id' => 3778, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 60, 'seance_id' => 13, 'eleve_id' => 3777, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 61, 'seance_id' => 13, 'eleve_id' => 3760, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 62, 'seance_id' => 13, 'eleve_id' => 3789, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 63, 'seance_id' => 13, 'eleve_id' => 3786, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 64, 'seance_id' => 13, 'eleve_id' => 3774, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 65, 'seance_id' => 13, 'eleve_id' => 3764, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 66, 'seance_id' => 13, 'eleve_id' => 3779, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 67, 'seance_id' => 13, 'eleve_id' => 3791, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 68, 'seance_id' => 13, 'eleve_id' => 3761, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 69, 'seance_id' => 13, 'eleve_id' => 3780, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 70, 'seance_id' => 13, 'eleve_id' => 3773, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 71, 'seance_id' => 13, 'eleve_id' => 3792, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 72, 'seance_id' => 13, 'eleve_id' => 3769, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 73, 'seance_id' => 13, 'eleve_id' => 3768, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 74, 'seance_id' => 13, 'eleve_id' => 3784, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 75, 'seance_id' => 13, 'eleve_id' => 3788, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 76, 'seance_id' => 13, 'eleve_id' => 3787, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 77, 'seance_id' => 13, 'eleve_id' => 3785, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 78, 'seance_id' => 13, 'eleve_id' => 3783, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 79, 'seance_id' => 13, 'eleve_id' => 3765, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 80, 'seance_id' => 13, 'eleve_id' => 3766, 'statut' => 'absent', 'motif' => 'scolarite', 'justifie' => 1, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:20:48'],
            ['id' => 81, 'seance_id' => 13, 'eleve_id' => 3790, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 82, 'seance_id' => 13, 'eleve_id' => 3763, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 83, 'seance_id' => 13, 'eleve_id' => 3776, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 84, 'seance_id' => 13, 'eleve_id' => 3781, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 85, 'seance_id' => 13, 'eleve_id' => 3767, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
            ['id' => 86, 'seance_id' => 13, 'eleve_id' => 3775, 'statut' => 'present', 'motif' => null, 'justifie' => 0, 'remarque' => null, 'created_at' => '2026-08-17 03:18:05', 'updated_at' => '2026-08-17 03:18:05'],
        ];
        DB::table($table)->insert($rows);

    }
}
