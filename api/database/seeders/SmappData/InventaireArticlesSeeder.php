<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: inventaire_articles
 * Lignes: 1
 */
class InventaireArticlesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'inventaire_articles';

        $rows = [
            ['id' => 3, 'school_id' => 1, 'nom' => 'Cartons de craies', 'categorie' => 'pedagogique', 'quantite' => 100, 'etat' => 'bon', 'localisation' => 'Direction', 'valeur_unitaire' => 5000, 'date_acquisition' => '2026-08-17', 'notes' => null, 'created_at' => '2026-08-17 14:14:46', 'updated_at' => '2026-08-17 14:15:03'],
        ];
        DB::table($table)->insert($rows);

    }
}
