<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: versement_lignes
 * Lignes: 4
 */
class VersementLignesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'versement_lignes';

        $rows = [
            ['id' => 27, 'versement_id' => 22, 'affectation' => 'scolarite', 'dossier_frais_annexe_id' => null, 'libelle' => 'Frais de scolarité', 'montant' => 50000, 'created_at' => '2026-08-16 18:57:02', 'updated_at' => '2026-08-16 18:57:02'],
            ['id' => 28, 'versement_id' => 23, 'affectation' => 'scolarite', 'dossier_frais_annexe_id' => null, 'libelle' => 'Avance sur scolarité', 'montant' => 25000, 'created_at' => '2026-08-17 02:15:54', 'updated_at' => '2026-08-17 02:15:54'],
            ['id' => 29, 'versement_id' => 24, 'affectation' => 'bus', 'dossier_frais_annexe_id' => null, 'libelle' => 'Transport scolaire — Trajet Sud', 'montant' => 15000, 'created_at' => '2026-08-17 03:26:07', 'updated_at' => '2026-08-17 03:26:07'],
            ['id' => 38, 'versement_id' => 29, 'affectation' => 'bus', 'dossier_frais_annexe_id' => null, 'libelle' => 'Transport scolaire — EKOUNOU - BONIS', 'montant' => 9000, 'created_at' => '2026-08-17 05:31:34', 'updated_at' => '2026-08-17 05:31:34'],
        ];
        DB::table($table)->insert($rows);

    }
}
