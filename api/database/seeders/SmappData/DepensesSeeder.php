<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: depenses
 * Lignes: 1
 */
class DepensesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'depenses';

        $rows = [
            ['id' => 17, 'school_id' => 2, 'annee_scolaire_id' => null, 'compte_comptable_id' => 36, 'date_depense' => '2026-08-16', 'libelle' => 'Férié 1', 'montant' => 50000, 'mode' => 'especes', 'beneficiaire' => null, 'reference_facture' => null, 'responsable' => null, 'saisi_par' => 1, 'justificatif_path' => null, 'statut' => 'payee', 'annule_le' => null, 'annule_par' => null, 'motif_annulation' => null, 'created_at' => '2026-08-16 19:00:47', 'updated_at' => '2026-08-16 19:00:47'],
        ];
        DB::table($table)->insert($rows);

    }
}
