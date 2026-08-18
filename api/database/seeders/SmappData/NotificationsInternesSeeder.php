<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: notifications_internes
 * Lignes: 5
 */
class NotificationsInternesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'notifications_internes';

        $rows = [
            ['id' => 10, 'school_id' => 1, 'user_id' => 1, 'type' => 'annonce', 'titre' => 'Rentrée Scolaire', 'message' => 'Bonne rentrée scolaire à tous !!', 'lien' => null, 'lu' => 1, 'lu_le' => '2026-08-17 08:15:32', 'created_at' => '2026-08-17 07:46:49', 'updated_at' => '2026-08-17 08:15:32'],
            ['id' => 11, 'school_id' => 1, 'user_id' => 4, 'type' => 'annonce', 'titre' => 'Rentrée Scolaire', 'message' => 'Bonne rentrée scolaire à tous !!', 'lien' => null, 'lu' => 0, 'lu_le' => null, 'created_at' => '2026-08-17 07:46:49', 'updated_at' => '2026-08-17 07:46:49'],
            ['id' => 12, 'school_id' => 1, 'user_id' => 5, 'type' => 'annonce', 'titre' => 'Rentrée Scolaire', 'message' => 'Bonne rentrée scolaire à tous !!', 'lien' => null, 'lu' => 0, 'lu_le' => null, 'created_at' => '2026-08-17 07:46:49', 'updated_at' => '2026-08-17 07:46:49'],
            ['id' => 13, 'school_id' => 1, 'user_id' => 8, 'type' => 'annonce', 'titre' => 'Rentrée Scolaire', 'message' => 'Bonne rentrée scolaire à tous !!', 'lien' => null, 'lu' => 0, 'lu_le' => null, 'created_at' => '2026-08-17 07:46:49', 'updated_at' => '2026-08-17 07:46:49'],
            ['id' => 14, 'school_id' => 1, 'user_id' => 9, 'type' => 'annonce', 'titre' => 'Rentrée Scolaire', 'message' => 'Bonne rentrée scolaire à tous !!', 'lien' => null, 'lu' => 0, 'lu_le' => null, 'created_at' => '2026-08-17 07:46:49', 'updated_at' => '2026-08-17 07:46:49'],
        ];
        DB::table($table)->insert($rows);

    }
}
