<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: permissions
 * Lignes: 38
 */
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'permissions';

        $rows = [
            ['id' => 1, 'name' => 'ecoles.manage', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 2, 'name' => 'personnel.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 3, 'name' => 'personnel.manage', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 4, 'name' => 'classes.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 5, 'name' => 'classes.manage', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 6, 'name' => 'eleves.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 7, 'name' => 'eleves.manage', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 8, 'name' => 'pedagogie.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 9, 'name' => 'pedagogie.manage', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 10, 'name' => 'notes.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 11, 'name' => 'notes.create', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 12, 'name' => 'discipline.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 13, 'name' => 'discipline.manage', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 14, 'name' => 'finance.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 15, 'name' => 'finance.manage', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 16, 'name' => 'bulletins.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 17, 'name' => 'bulletins.publish', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 18, 'name' => 'annonces.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 19, 'name' => 'annonces.publish', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 20, 'name' => 'emploi_du_temps.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 21, 'name' => 'emploi_du_temps.manage', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 22, 'name' => 'appel.manage', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:53', 'updated_at' => '2026-08-10 19:27:53'],
            ['id' => 23, 'name' => 'dashboard.view', 'guard_name' => 'web', 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-10 19:27:54'],
            ['id' => 24, 'name' => 'niveaux.view', 'guard_name' => 'web', 'created_at' => '2026-08-11 22:58:18', 'updated_at' => '2026-08-11 22:58:18'],
            ['id' => 25, 'name' => 'niveaux.manage', 'guard_name' => 'web', 'created_at' => '2026-08-11 22:58:18', 'updated_at' => '2026-08-11 22:58:18'],
            ['id' => 26, 'name' => 'finance.encaisser', 'guard_name' => 'web', 'created_at' => '2026-08-16 13:24:49', 'updated_at' => '2026-08-16 13:24:49'],
            ['id' => 27, 'name' => 'finance.annuler', 'guard_name' => 'web', 'created_at' => '2026-08-16 13:24:49', 'updated_at' => '2026-08-16 13:24:49'],
            ['id' => 28, 'name' => 'finance.depenses', 'guard_name' => 'web', 'created_at' => '2026-08-16 13:24:49', 'updated_at' => '2026-08-16 13:24:49'],
            ['id' => 29, 'name' => 'finance.paie', 'guard_name' => 'web', 'created_at' => '2026-08-16 13:24:49', 'updated_at' => '2026-08-16 13:24:49'],
            ['id' => 30, 'name' => 'finance.rapports', 'guard_name' => 'web', 'created_at' => '2026-08-16 13:24:49', 'updated_at' => '2026-08-16 13:24:49'],
            ['id' => 31, 'name' => 'infirmerie.view', 'guard_name' => 'web', 'created_at' => '2026-08-17 01:24:02', 'updated_at' => '2026-08-17 01:24:02'],
            ['id' => 32, 'name' => 'infirmerie.manage', 'guard_name' => 'web', 'created_at' => '2026-08-17 01:24:02', 'updated_at' => '2026-08-17 01:24:02'],
            ['id' => 33, 'name' => 'bus.view', 'guard_name' => 'web', 'created_at' => '2026-08-17 01:24:02', 'updated_at' => '2026-08-17 01:24:02'],
            ['id' => 34, 'name' => 'bus.manage', 'guard_name' => 'web', 'created_at' => '2026-08-17 01:24:02', 'updated_at' => '2026-08-17 01:24:02'],
            ['id' => 35, 'name' => 'inventaire.view', 'guard_name' => 'web', 'created_at' => '2026-08-17 07:26:45', 'updated_at' => '2026-08-17 07:26:45'],
            ['id' => 36, 'name' => 'inventaire.manage', 'guard_name' => 'web', 'created_at' => '2026-08-17 07:26:45', 'updated_at' => '2026-08-17 07:26:45'],
            ['id' => 37, 'name' => 'revendications.view', 'guard_name' => 'web', 'created_at' => '2026-08-17 22:12:31', 'updated_at' => '2026-08-17 22:12:31'],
            ['id' => 38, 'name' => 'revendications.manage', 'guard_name' => 'web', 'created_at' => '2026-08-17 22:12:31', 'updated_at' => '2026-08-17 22:12:31'],
        ];
        DB::table($table)->insert($rows);

    }
}
