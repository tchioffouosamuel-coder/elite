<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: settings
 * Lignes: 50
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'settings';

        $rows = [
            ['id' => 1, 'school_id' => 1, 'key' => 'num_sequences', 'value' => '2', 'created_at' => '2026-08-10 19:27:55', 'updated_at' => '2026-08-10 19:27:55'],
            ['id' => 2, 'school_id' => 1, 'key' => 'empty_cancel', 'value' => 'cancel', 'created_at' => '2026-08-10 19:27:55', 'updated_at' => '2026-08-10 19:27:55'],
            ['id' => 3, 'school_id' => 1, 'key' => 'honour_roll', 'value' => '14', 'created_at' => '2026-08-10 19:27:55', 'updated_at' => '2026-08-10 19:27:55'],
            ['id' => 4, 'school_id' => 1, 'key' => 'honour_attendance_max', 'value' => '20', 'created_at' => '2026-08-10 19:27:55', 'updated_at' => '2026-08-10 19:27:55'],
            ['id' => 5, 'school_id' => 1, 'key' => 'min_coef_per', 'value' => '50', 'created_at' => '2026-08-10 19:27:55', 'updated_at' => '2026-08-10 19:27:55'],
            ['id' => 6, 'school_id' => 3, 'key' => 'num_sequences', 'value' => '2', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 7, 'school_id' => 3, 'key' => 'passage_moyenne_min', 'value' => '10', 'created_at' => '2026-08-10 19:28:02', 'updated_at' => '2026-08-10 19:28:02'],
            ['id' => 8, 'school_id' => 2, 'key' => 'num_sequences', 'value' => '2', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-12 03:26:37'],
            ['id' => 9, 'school_id' => 2, 'key' => 'passage_moyenne_min', 'value' => '10', 'created_at' => '2026-08-10 19:28:48', 'updated_at' => '2026-08-10 19:28:48'],
            ['id' => 10, 'school_id' => 1, 'key' => 'felicitations_min', 'value' => '16', 'created_at' => '2026-08-11 04:56:51', 'updated_at' => '2026-08-11 04:56:51'],
            ['id' => 11, 'school_id' => 1, 'key' => 'encouragements_min', 'value' => '14', 'created_at' => '2026-08-11 04:56:51', 'updated_at' => '2026-08-11 04:56:51'],
            ['id' => 12, 'school_id' => 1, 'key' => 'avertissement_travail_min', 'value' => '8', 'created_at' => '2026-08-11 04:56:51', 'updated_at' => '2026-08-11 04:56:51'],
            ['id' => 13, 'school_id' => 1, 'key' => 'avertissement_travail_max', 'value' => '10', 'created_at' => '2026-08-11 04:56:51', 'updated_at' => '2026-08-11 04:56:51'],
            ['id' => 14, 'school_id' => 1, 'key' => 'blame_travail_max', 'value' => '8', 'created_at' => '2026-08-11 04:56:51', 'updated_at' => '2026-08-11 04:56:51'],
            ['id' => 15, 'school_id' => 1, 'key' => 'avertissement_conduite_min', 'value' => '10', 'created_at' => '2026-08-11 04:56:51', 'updated_at' => '2026-08-11 04:56:51'],
            ['id' => 16, 'school_id' => 1, 'key' => 'avertissement_conduite_max', 'value' => '20', 'created_at' => '2026-08-11 04:56:51', 'updated_at' => '2026-08-11 04:56:51'],
            ['id' => 17, 'school_id' => 1, 'key' => 'blame_conduite_min', 'value' => '20', 'created_at' => '2026-08-11 04:56:51', 'updated_at' => '2026-08-11 04:56:51'],
            ['id' => 18, 'school_id' => 1, 'key' => 'passage_moyenne_min', 'value' => '10', 'created_at' => '2026-08-11 04:56:51', 'updated_at' => '2026-08-11 04:56:51'],
            ['id' => 19, 'school_id' => 1, 'key' => 'chef_etablissement_titre', 'value' => 'Le Chef d\'Établissement', 'created_at' => '2026-08-11 04:56:51', 'updated_at' => '2026-08-11 04:56:51'],
            ['id' => 20, 'school_id' => 3, 'key' => 'empty_cancel', 'value' => 'cancel', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 21, 'school_id' => 3, 'key' => 'min_coef_per', 'value' => '50', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 22, 'school_id' => 3, 'key' => 'honour_roll', 'value' => '14', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 23, 'school_id' => 3, 'key' => 'honour_attendance_max', 'value' => '20', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 24, 'school_id' => 3, 'key' => 'felicitations_min', 'value' => '16', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 25, 'school_id' => 3, 'key' => 'encouragements_min', 'value' => '14', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 26, 'school_id' => 3, 'key' => 'avertissement_travail_min', 'value' => '8', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 27, 'school_id' => 3, 'key' => 'avertissement_travail_max', 'value' => '10', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 28, 'school_id' => 3, 'key' => 'blame_travail_max', 'value' => '8', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 29, 'school_id' => 3, 'key' => 'avertissement_conduite_min', 'value' => '10', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 30, 'school_id' => 3, 'key' => 'avertissement_conduite_max', 'value' => '20', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 31, 'school_id' => 3, 'key' => 'blame_conduite_min', 'value' => '20', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-11 05:31:46'],
            ['id' => 32, 'school_id' => 3, 'key' => 'chef_etablissement_titre', 'value' => 'La Directrice', 'created_at' => '2026-08-11 05:31:46', 'updated_at' => '2026-08-16 09:46:19'],
            ['id' => 33, 'school_id' => 1, 'key' => 'chef_etablissement', 'value' => 'USENI Venyteh', 'created_at' => '2026-08-11 06:11:59', 'updated_at' => '2026-08-11 06:11:59'],
            ['id' => 34, 'school_id' => 2, 'key' => 'empty_cancel', 'value' => 'cancel', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 35, 'school_id' => 2, 'key' => 'min_coef_per', 'value' => '50', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 36, 'school_id' => 2, 'key' => 'honour_roll', 'value' => '14', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 37, 'school_id' => 2, 'key' => 'honour_attendance_max', 'value' => '20', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 38, 'school_id' => 2, 'key' => 'felicitations_min', 'value' => '16', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 39, 'school_id' => 2, 'key' => 'encouragements_min', 'value' => '14', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 40, 'school_id' => 2, 'key' => 'avertissement_travail_min', 'value' => '8', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 41, 'school_id' => 2, 'key' => 'avertissement_travail_max', 'value' => '10', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 42, 'school_id' => 2, 'key' => 'blame_travail_max', 'value' => '8', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 43, 'school_id' => 2, 'key' => 'avertissement_conduite_min', 'value' => '10', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 44, 'school_id' => 2, 'key' => 'avertissement_conduite_max', 'value' => '20', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 45, 'school_id' => 2, 'key' => 'blame_conduite_min', 'value' => '20', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 46, 'school_id' => 2, 'key' => 'chef_etablissement_titre', 'value' => 'Le Chef d\'Établissement', 'created_at' => '2026-08-12 01:34:19', 'updated_at' => '2026-08-12 01:34:19'],
            ['id' => 48, 'school_id' => 2, 'key' => 'mentions_legales', 'value' => '<p><strong><em>Autorisation d’ouverture N° : 102/J1/7/A/MINEDUB/SG/DSEPB/SDAAP DU 29 JUIN 2015</em></strong><br><strong><em>Extension authorisation for primary N°:047/J1/A/MINEDUB/SG/DSEPB/SDAAP of 25 May 2016</em></strong><br><strong><em>Compte banque NTARINKON N° 2704-006. RC N°M051612573058R. CNPS N°: 330-0112138-000-C</em></strong></p>', 'created_at' => '2026-08-16 06:27:59', 'updated_at' => '2026-08-16 06:27:59'],
            ['id' => 49, 'school_id' => 3, 'key' => 'chef_etablissement', 'value' => 'FONGWI QUINTA Epse FOMESSO CHARLOTTE', 'created_at' => '2026-08-16 09:46:19', 'updated_at' => '2026-08-16 09:46:19'],
            ['id' => 50, 'school_id' => 3, 'key' => 'mentions_legales', 'value' => '<p><strong><em>Autorisation d’ouverture N° : 102/J1/7/A/MINEDUB/SG/DSEPB/SDAAP DU 29 JUIN 2015</em></strong><br><strong><em>Extension authorisation for primary N°:047/J1/A/MINEDUB/SG/DSEPB/SDAAP of 25 May 2016</em></strong><br><strong><em>Compte banque NTARINKON N° 2704-006. RC N°M051612573058R. CNPS N°: 330-0112138-000-C</em></strong></p>', 'created_at' => '2026-08-16 09:46:19', 'updated_at' => '2026-08-16 09:46:19'],
            ['id' => 51, 'school_id' => 1, 'key' => 'mentions_legales', 'value' => '<p><strong><em>Autorisation d’ouverture N° : 102/J1/7/A/MINEDUB/SG/DSEPB/SDAAP DU 29 JUIN 2015</em></strong><br><strong><em>Extension authorisation for primary N°:047/J1/A/MINEDUB/SG/DSEPB/SDAAP of 25 May 2016</em></strong><br><strong><em>Compte banque NTARINKON N° 2704-006. RC N°M051612573058R. CNPS N°: 330-0112138-000-C</em></strong></p>', 'created_at' => '2026-08-17 13:53:28', 'updated_at' => '2026-08-17 13:53:28'],
        ];
        DB::table($table)->insert($rows);

    }
}
