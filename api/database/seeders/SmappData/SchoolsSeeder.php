<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: schools
 * Lignes: 3
 */
class SchoolsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'schools';

        $rows = [
            ['id' => 1, 'complexe_id' => 1, 'name' => 'Elites Bilingual Technical and Commercial College', 'code' => 'ELITES-BTA', 'type' => 'secondaire', 'logo_path' => 'ecoles/1/logo.jpg', 'stamp_path' => 'ecoles/1/cachet.png', 'signature_path' => 'ecoles/1/signature.png', 'address' => 'Bertoua-Monou2, Cameroun', 'phone' => '698256973', 'email' => null, 'header_fr' => '<p><strong>REPUBLIQUE DU CAMEROUN<br>**********<br><em>Paix - Travail - Patrie</em></strong></p><p>MINISTERE DES ENSEIGNEMENTS SECONDAIRES</p><p>REGION DE L\'EST<br>DEPARTEMENT DU LOM ET DJEREM<br><strong>COLLEGE TECHNIQUE ET COMMERCIAL BILNGUE ELITE</strong></p>', 'header_en' => '<p><strong>REPUBLIC OF CAMEROON<em><br>**********<br>Peace - Work - Fatherland</em></strong><br>MINISTRY OF SECONDARY EDUCATION<br>EAST REGION<br>LOM AND DJEREM DIVISION<br><strong>ELITES BILINGUAL TECHNICAL AND COMMERCIAL COLLEGE</strong></p>', 'is_active' => 1, 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-17 13:55:58'],
            ['id' => 2, 'complexe_id' => 1, 'name' => 'Elites Bilingual Nursery School', 'code' => 'ELITES-MAT', 'type' => 'maternelle', 'logo_path' => 'ecoles/2/logo.png', 'stamp_path' => 'ecoles/2/cachet.png', 'signature_path' => 'ecoles/2/signature.png', 'address' => 'Bertoua-Monou2, Cameroun', 'phone' => null, 'email' => null, 'header_fr' => '<p><strong>REPUBLIQUE DU CAMEROUN<br>**********<br><em>Paix - Travail - Patrie</em></strong></p><p>MINISTERE DE L\'EDUCATION DE BASE</p><p>REGION DE L\'EST<br>DEPARTEMENT DU LOM ET DJEREM<br><strong>ECOLE MATERNELLE LAÏQUE LES ELITES</strong></p>', 'header_en' => '<p><strong>REPUBLIC OF CAMEROON<em><br>**********<br>Peace - Work - Fatherland</em></strong><br>MINISTRY OF BASIC EDUCATION<br>EAST REGION<br>LOM AND DJEREM DIVISION<br><strong>ELITES NUSERY LAY SCHOOL</strong></p>', 'is_active' => 1, 'created_at' => '2026-08-10 19:27:54', 'updated_at' => '2026-08-16 05:51:26'],
            ['id' => 3, 'complexe_id' => 1, 'name' => 'Elites Bilingual Primary School', 'code' => 'ELITES-PRI', 'type' => 'primaire', 'logo_path' => 'ecoles/3/logo.png', 'stamp_path' => 'ecoles/3/cachet.png', 'signature_path' => 'ecoles/3/signature.png', 'address' => 'Bertoua-Monou2, Cameroun', 'phone' => null, 'email' => null, 'header_fr' => '<p><strong>REPUBLIQUE DU CAMEROUN<br>**********<br><em>Paix - Travail - Patrie</em></strong></p><p>MINISTERE DE L\'EDUCATION DE BASE</p><p>REGION DE L\'EST<br>DEPARTEMENT DU LOM ET DJEREM<br><strong>ECOLE PRIMAIRE LAÏQUE LES ELITES</strong></p>', 'header_en' => '<p><strong>REPUBLIC OF CAMEROON<em><br>**********<br>Peace - Work - Fatherland</em></strong><br>MINISTRY OF BASIC EDUCATION<br>EAST REGION<br>LOM AND DJEREM DIVISION<br><strong>ELITES PRIMARY LAY SCHOOL</strong></p>', 'is_active' => 1, 'created_at' => '2026-08-10 19:27:55', 'updated_at' => '2026-08-16 09:45:11'],
        ];
        DB::table($table)->insert($rows);

    }
}
