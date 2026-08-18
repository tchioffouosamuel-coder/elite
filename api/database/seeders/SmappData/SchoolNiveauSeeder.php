<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: school_niveau
 * Lignes: 8
 */
class SchoolNiveauSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'school_niveau';

        $rows = [
            ['id' => 4, 'school_id' => 2, 'niveau_id' => 4, 'created_at' => null, 'updated_at' => null],
            ['id' => 5, 'school_id' => 2, 'niveau_id' => 5, 'created_at' => null, 'updated_at' => null],
            ['id' => 6, 'school_id' => 2, 'niveau_id' => 6, 'created_at' => null, 'updated_at' => null],
            ['id' => 7, 'school_id' => 2, 'niveau_id' => 7, 'created_at' => null, 'updated_at' => null],
            ['id' => 8, 'school_id' => 2, 'niveau_id' => 8, 'created_at' => null, 'updated_at' => null],
            ['id' => 9, 'school_id' => 2, 'niveau_id' => 9, 'created_at' => null, 'updated_at' => null],
            ['id' => 10, 'school_id' => 2, 'niveau_id' => 10, 'created_at' => null, 'updated_at' => null],
            ['id' => 11, 'school_id' => 2, 'niveau_id' => 11, 'created_at' => null, 'updated_at' => null],
        ];
        DB::table($table)->insert($rows);

    }
}
