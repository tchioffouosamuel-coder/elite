<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: fonction_referentiel
 * Lignes: 36
 */
class FonctionReferentielSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'fonction_referentiel';

        $rows = [
            ['id' => 1, 'school_id' => 1, 'label_fr' => 'Enseignant', 'label_en' => 'Teacher'],
            ['id' => 2, 'school_id' => 1, 'label_fr' => 'Principal', 'label_en' => 'Principal'],
            ['id' => 3, 'school_id' => 1, 'label_fr' => 'Directeur', 'label_en' => 'Headmaster'],
            ['id' => 4, 'school_id' => 1, 'label_fr' => 'Censeur', 'label_en' => 'Vice-Principal'],
            ['id' => 5, 'school_id' => 1, 'label_fr' => 'Surveillant Général', 'label_en' => 'Discipline Master'],
            ['id' => 6, 'school_id' => 1, 'label_fr' => 'Conseiller d\'orientation', 'label_en' => 'Guidance Counsellor'],
            ['id' => 7, 'school_id' => 1, 'label_fr' => 'Économe', 'label_en' => 'Bursar'],
            ['id' => 8, 'school_id' => 1, 'label_fr' => 'Secrétaire', 'label_en' => 'Secretary'],
            ['id' => 9, 'school_id' => 1, 'label_fr' => 'Documentaliste', 'label_en' => 'Librarian'],
            ['id' => 10, 'school_id' => 1, 'label_fr' => 'Infirmier', 'label_en' => 'Nurse'],
            ['id' => 11, 'school_id' => 1, 'label_fr' => 'Gardien', 'label_en' => 'Security Guard'],
            ['id' => 12, 'school_id' => 1, 'label_fr' => 'Agent d\'entretien', 'label_en' => 'Cleaner'],
            ['id' => 13, 'school_id' => 2, 'label_fr' => 'Enseignant', 'label_en' => 'Teacher'],
            ['id' => 14, 'school_id' => 2, 'label_fr' => 'Principal', 'label_en' => 'Principal'],
            ['id' => 15, 'school_id' => 2, 'label_fr' => 'Directeur', 'label_en' => 'Headmaster'],
            ['id' => 16, 'school_id' => 2, 'label_fr' => 'Censeur', 'label_en' => 'Vice-Principal'],
            ['id' => 17, 'school_id' => 2, 'label_fr' => 'Surveillant Général', 'label_en' => 'Discipline Master'],
            ['id' => 18, 'school_id' => 2, 'label_fr' => 'Conseiller d\'orientation', 'label_en' => 'Guidance Counsellor'],
            ['id' => 19, 'school_id' => 2, 'label_fr' => 'Économe', 'label_en' => 'Bursar'],
            ['id' => 20, 'school_id' => 2, 'label_fr' => 'Secrétaire', 'label_en' => 'Secretary'],
            ['id' => 21, 'school_id' => 2, 'label_fr' => 'Documentaliste', 'label_en' => 'Librarian'],
            ['id' => 22, 'school_id' => 2, 'label_fr' => 'Infirmier', 'label_en' => 'Nurse'],
            ['id' => 23, 'school_id' => 2, 'label_fr' => 'Gardien', 'label_en' => 'Security Guard'],
            ['id' => 24, 'school_id' => 2, 'label_fr' => 'Agent d\'entretien', 'label_en' => 'Cleaner'],
            ['id' => 25, 'school_id' => 3, 'label_fr' => 'Enseignant', 'label_en' => 'Teacher'],
            ['id' => 26, 'school_id' => 3, 'label_fr' => 'Principal', 'label_en' => 'Principal'],
            ['id' => 27, 'school_id' => 3, 'label_fr' => 'Directeur', 'label_en' => 'Headmaster'],
            ['id' => 28, 'school_id' => 3, 'label_fr' => 'Censeur', 'label_en' => 'Vice-Principal'],
            ['id' => 29, 'school_id' => 3, 'label_fr' => 'Surveillant Général', 'label_en' => 'Discipline Master'],
            ['id' => 30, 'school_id' => 3, 'label_fr' => 'Conseiller d\'orientation', 'label_en' => 'Guidance Counsellor'],
            ['id' => 31, 'school_id' => 3, 'label_fr' => 'Économe', 'label_en' => 'Bursar'],
            ['id' => 32, 'school_id' => 3, 'label_fr' => 'Secrétaire', 'label_en' => 'Secretary'],
            ['id' => 33, 'school_id' => 3, 'label_fr' => 'Documentaliste', 'label_en' => 'Librarian'],
            ['id' => 34, 'school_id' => 3, 'label_fr' => 'Infirmier', 'label_en' => 'Nurse'],
            ['id' => 35, 'school_id' => 3, 'label_fr' => 'Gardien', 'label_en' => 'Security Guard'],
            ['id' => 36, 'school_id' => 3, 'label_fr' => 'Agent d\'entretien', 'label_en' => 'Cleaner'],
        ];
        DB::table($table)->insert($rows);

    }
}
