<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: matieres
 * Lignes: 23
 */
class MatieresSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'matieres';

        $rows = [
            ['id' => 25, 'school_id' => 1, 'departement_id' => 1, 'nom' => 'Chimie', 'nom_en' => null, 'abbreviation' => 'CHM', 'notation' => null, 'evalue_pratique' => 0, 'repartition_volets' => null, 'statut' => 'actif', 'created_at' => '2026-08-12 03:01:32', 'updated_at' => '2026-08-12 03:01:32'],
            ['id' => 121, 'school_id' => 2, 'departement_id' => null, 'nom' => 'INITIATION TO READING', 'nom_en' => 'IN READING', 'abbreviation' => 'IN READ', 'notation' => 20, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 10, "ecrit": 5, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 122, 'school_id' => 2, 'departement_id' => null, 'nom' => 'NATIONAL LANGUAGE', 'nom_en' => 'NATIONAL LANGUAGE', 'abbreviation' => 'NAT LANG', 'notation' => 20, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 10, "ecrit": 5, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 123, 'school_id' => 2, 'departement_id' => null, 'nom' => 'WRITING', 'nom_en' => 'WRITING', 'abbreviation' => 'WRIT', 'notation' => 20, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 5, "ecrit": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 124, 'school_id' => 2, 'departement_id' => null, 'nom' => 'SCIENCE AND TECHNOLOGIE', 'nom_en' => 'SCIENCE AND TECHNOLOGIE', 'abbreviation' => 'SCI TECH', 'notation' => 20, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 5, "ecrit": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 125, 'school_id' => 2, 'departement_id' => null, 'nom' => 'FREE PLAY', 'nom_en' => 'FREE PLAY', 'abbreviation' => 'FREE PLAY', 'notation' => 20, 'evalue_pratique' => 1, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "pratique": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 126, 'school_id' => 2, 'departement_id' => null, 'nom' => 'SPORT', 'nom_en' => 'SPORT', 'abbreviation' => 'SPORT', 'notation' => 20, 'evalue_pratique' => 1, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "pratique": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 127, 'school_id' => 2, 'departement_id' => null, 'nom' => 'EXPRESSION BY GESTURE', 'nom_en' => 'EXPRESSION BY GESTURE', 'abbreviation' => 'EXPR GEST', 'notation' => 20, 'evalue_pratique' => 1, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "pratique": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 128, 'school_id' => 2, 'departement_id' => null, 'nom' => 'RHYME/POEM/MUSIC', 'nom_en' => 'RHYME/POEM/MUSIC', 'abbreviation' => 'RHY MUS', 'notation' => 20, 'evalue_pratique' => 1, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "pratique": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 129, 'school_id' => 2, 'departement_id' => null, 'nom' => 'SONG/RHYME', 'nom_en' => 'SONG/RHYME', 'abbreviation' => 'SONG', 'notation' => 20, 'evalue_pratique' => 1, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "pratique": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 130, 'school_id' => 2, 'departement_id' => null, 'nom' => 'DRAWING/COLORING', 'nom_en' => 'DRAWING/COLORING', 'abbreviation' => 'DRAW COL', 'notation' => 20, 'evalue_pratique' => 1, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "pratique": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 131, 'school_id' => 2, 'departement_id' => null, 'nom' => 'DRAWING/PAINTING', 'nom_en' => 'DRAWING/PAINTING', 'abbreviation' => 'DRAW PAINT', 'notation' => 20, 'evalue_pratique' => 1, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "pratique": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 132, 'school_id' => 2, 'departement_id' => null, 'nom' => 'SENSORY', 'nom_en' => 'SENSORY', 'abbreviation' => 'SENSORY', 'notation' => 20, 'evalue_pratique' => 1, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "pratique": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 133, 'school_id' => 2, 'departement_id' => null, 'nom' => 'ICT', 'nom_en' => 'ICT', 'abbreviation' => 'ICT', 'notation' => 20, 'evalue_pratique' => 1, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "pratique": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 134, 'school_id' => 2, 'departement_id' => null, 'nom' => 'AGRICULTURE', 'nom_en' => 'AGRICULTURE', 'abbreviation' => 'AGRIC', 'notation' => 20, 'evalue_pratique' => 1, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "pratique": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 135, 'school_id' => 2, 'departement_id' => null, 'nom' => 'CHARACTER EDUCATION', 'nom_en' => 'CHARACTER EDUCATION', 'abbreviation' => 'CHAR EDUC', 'notation' => 20, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "savoir_etre": 15}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 136, 'school_id' => 2, 'departement_id' => null, 'nom' => 'HEALTH EDUCATION', 'nom_en' => 'HEALTH EDUCATION', 'abbreviation' => 'HEALTH', 'notation' => 20, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "savoir_etre": 15}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 137, 'school_id' => 2, 'departement_id' => null, 'nom' => 'CITIZENSHIP', 'nom_en' => 'CITIZENSHIP', 'abbreviation' => 'CITIZEN', 'notation' => 20, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "savoir_etre": 15}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 138, 'school_id' => 2, 'departement_id' => null, 'nom' => 'MORAL EDUCATION', 'nom_en' => 'MORAL EDU', 'abbreviation' => 'MORAL', 'notation' => 20, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "savoir_etre": 15}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 139, 'school_id' => 2, 'departement_id' => null, 'nom' => 'SAFETY EDUCATION', 'nom_en' => 'SAFETY EDUCATION', 'abbreviation' => 'SAFETY', 'notation' => 20, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 5, "ecrit": 0, "savoir_etre": 15}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:01', 'updated_at' => '2026-08-12 04:21:01'],
            ['id' => 140, 'school_id' => 2, 'departement_id' => null, 'nom' => 'Initiation to Mathematics', 'nom_en' => 'Initiation to Mathematics', 'abbreviation' => 'IN MATHS', 'notation' => 40, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 15, "ecrit": 20, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:21:41', 'updated_at' => '2026-08-12 04:21:41'],
            ['id' => 141, 'school_id' => 2, 'departement_id' => null, 'nom' => 'ENVIRONMENTAL EDUCATION', 'nom_en' => 'ENVIRONMENTAL EDUCATION', 'abbreviation' => 'ENV. EDUC.', 'notation' => 40, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 20, "ecrit": 15, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-12 04:22:01', 'updated_at' => '2026-08-12 04:22:01'],
            ['id' => 188, 'school_id' => 3, 'departement_id' => null, 'nom' => 'Français', 'nom_en' => null, 'abbreviation' => 'FR', 'notation' => 20, 'evalue_pratique' => 0, 'repartition_volets' => '{"oral": 5, "ecrit": 10, "savoir_etre": 5}', 'statut' => 'actif', 'created_at' => '2026-08-17 02:52:07', 'updated_at' => '2026-08-17 02:52:07'],
        ];
        DB::table($table)->insert($rows);

    }
}
