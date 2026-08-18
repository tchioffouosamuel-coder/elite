<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: document_references
 * Lignes: 21
 */
class DocumentReferencesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'document_references';

        $rows = [
            ['id' => 38, 'school_id' => 2, 'type' => 'recu_scolarite', 'annee_scolaire_id' => 1, 'numero' => 1, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => 1, 'created_at' => '2026-08-16 18:57:02', 'updated_at' => '2026-08-16 18:57:02'],
            ['id' => 39, 'school_id' => 2, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 1, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:49:02', 'updated_at' => '2026-08-16 19:49:02'],
            ['id' => 40, 'school_id' => 2, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 2, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:49:02', 'updated_at' => '2026-08-16 19:49:02'],
            ['id' => 41, 'school_id' => 2, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 3, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:49:02', 'updated_at' => '2026-08-16 19:49:02'],
            ['id' => 42, 'school_id' => 2, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 4, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:49:02', 'updated_at' => '2026-08-16 19:49:02'],
            ['id' => 43, 'school_id' => 2, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 5, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:49:02', 'updated_at' => '2026-08-16 19:49:02'],
            ['id' => 44, 'school_id' => 2, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 6, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:49:02', 'updated_at' => '2026-08-16 19:49:02'],
            ['id' => 45, 'school_id' => 2, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 7, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:49:02', 'updated_at' => '2026-08-16 19:49:02'],
            ['id' => 46, 'school_id' => 2, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 8, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:49:02', 'updated_at' => '2026-08-16 19:49:02'],
            ['id' => 47, 'school_id' => 2, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 9, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:49:02', 'updated_at' => '2026-08-16 19:49:02'],
            ['id' => 48, 'school_id' => 2, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 10, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:49:02', 'updated_at' => '2026-08-16 19:49:02'],
            ['id' => 49, 'school_id' => 3, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 1, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:59:38', 'updated_at' => '2026-08-16 19:59:38'],
            ['id' => 50, 'school_id' => 3, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 2, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:59:38', 'updated_at' => '2026-08-16 19:59:38'],
            ['id' => 51, 'school_id' => 3, 'type' => 'bulletin_paie', 'annee_scolaire_id' => null, 'numero' => 3, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => null, 'created_at' => '2026-08-16 19:59:38', 'updated_at' => '2026-08-16 19:59:38'],
            ['id' => 52, 'school_id' => 1, 'type' => 'recu_scolarite', 'annee_scolaire_id' => 3, 'numero' => 1, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => 1, 'created_at' => '2026-08-17 02:15:54', 'updated_at' => '2026-08-17 02:15:54'],
            ['id' => 53, 'school_id' => 3, 'type' => 'recu_scolarite', 'annee_scolaire_id' => 2, 'numero' => 1, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => 1, 'created_at' => '2026-08-17 03:26:07', 'updated_at' => '2026-08-17 03:26:07'],
            ['id' => 56, 'school_id' => 2, 'type' => 'recu_scolarite', 'annee_scolaire_id' => 1, 'numero' => 2, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => 2, 'created_at' => '2026-08-17 04:01:59', 'updated_at' => '2026-08-17 04:01:59'],
            ['id' => 57, 'school_id' => 2, 'type' => 'recu_scolarite', 'annee_scolaire_id' => 1, 'numero' => 3, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => 2, 'created_at' => '2026-08-17 05:28:03', 'updated_at' => '2026-08-17 05:28:03'],
            ['id' => 58, 'school_id' => 2, 'type' => 'recu_scolarite', 'annee_scolaire_id' => 1, 'numero' => 4, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => 1, 'created_at' => '2026-08-17 05:31:34', 'updated_at' => '2026-08-17 05:31:34'],
            ['id' => 60, 'school_id' => 1, 'type' => 'certificat_scolarite', 'annee_scolaire_id' => 1, 'numero' => 1, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => 1, 'created_at' => '2026-08-17 13:52:11', 'updated_at' => '2026-08-17 13:52:11'],
            ['id' => 61, 'school_id' => 1, 'type' => 'certificat_scolarite', 'annee_scolaire_id' => 1, 'numero' => 2, 'referencable_type' => null, 'referencable_id' => null, 'genere_par' => 1, 'created_at' => '2026-08-17 13:56:12', 'updated_at' => '2026-08-17 13:56:12'],
        ];
        DB::table($table)->insert($rows);

    }
}
