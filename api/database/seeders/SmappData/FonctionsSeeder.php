<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: fonctions
 * Lignes: 12
 */
class FonctionsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'fonctions';

        $rows = [
            ['id' => 1, 'code' => 'enseignant', 'libelle' => 'Enseignant', 'libelle_en' => 'Teacher', 'categorie' => 'enseignement', 'ordre' => 1, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 2, 'code' => 'principal', 'libelle' => 'Principal', 'libelle_en' => 'Principal', 'categorie' => 'direction', 'ordre' => 2, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 3, 'code' => 'directeur', 'libelle' => 'Directeur', 'libelle_en' => 'Headmaster', 'categorie' => 'direction', 'ordre' => 3, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 4, 'code' => 'censeur', 'libelle' => 'Censeur', 'libelle_en' => 'Vice-Principal', 'categorie' => 'direction', 'ordre' => 4, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 5, 'code' => 'surveillant_general', 'libelle' => 'Surveillant Général', 'libelle_en' => 'Discipline Master', 'categorie' => 'direction', 'ordre' => 5, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 6, 'code' => 'conseiller_orientation', 'libelle' => 'Conseiller d\'orientation', 'libelle_en' => 'Guidance Counsellor', 'categorie' => 'direction', 'ordre' => 6, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 7, 'code' => 'econome', 'libelle' => 'Économe', 'libelle_en' => 'Bursar', 'categorie' => 'administration', 'ordre' => 7, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 8, 'code' => 'secretaire', 'libelle' => 'Secrétaire', 'libelle_en' => 'Secretary', 'categorie' => 'administration', 'ordre' => 8, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 9, 'code' => 'documentaliste', 'libelle' => 'Documentaliste', 'libelle_en' => 'Librarian', 'categorie' => 'administration', 'ordre' => 9, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 10, 'code' => 'infirmier', 'libelle' => 'Infirmier', 'libelle_en' => 'Nurse', 'categorie' => 'appui', 'ordre' => 10, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 11, 'code' => 'gardien', 'libelle' => 'Gardien', 'libelle_en' => 'Security Guard', 'categorie' => 'appui', 'ordre' => 11, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
            ['id' => 12, 'code' => 'agent_entretien', 'libelle' => 'Agent d\'entretien', 'libelle_en' => 'Cleaner', 'categorie' => 'appui', 'ordre' => 12, 'is_active' => 1, 'created_at' => '2026-08-11 22:12:12', 'updated_at' => '2026-08-11 22:12:12'],
        ];
        DB::table($table)->insert($rows);

    }
}
