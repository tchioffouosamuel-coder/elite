<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: evaluation_questions
 * Lignes: 1
 */
class EvaluationQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'evaluation_questions';

        $rows = [
            ['id' => 1, 'evaluation_id' => 1, 'enonce' => 'Qu\'est-ce qu\'un acide ?', 'bareme_question' => 20, 'ordre' => 1, 'created_at' => '2026-08-17 01:38:49', 'updated_at' => '2026-08-17 01:38:49'],
        ];
        DB::table($table)->insert($rows);

    }
}
