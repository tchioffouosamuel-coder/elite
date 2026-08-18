<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: niveaux
 * Lignes: 75
 */
class NiveauxSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'niveaux';

        $rows = [
            ['id' => 4, 'code' => 'BABY', 'name_fr' => 'Baby Section', 'name_en' => 'Baby Section', 'sous_system_id' => 6, 'school_id' => 2, 'ordre' => 1, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:45:57'],
            ['id' => 5, 'code' => 'BEBE', 'name_fr' => 'Bébé Section', 'name_en' => 'Baby Section', 'sous_system_id' => 5, 'school_id' => 2, 'ordre' => 2, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:46:05'],
            ['id' => 6, 'code' => 'GS', 'name_fr' => 'Grande Section', 'name_en' => 'Upper Kindergarten', 'sous_system_id' => 5, 'school_id' => 2, 'ordre' => 3, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:46:11'],
            ['id' => 7, 'code' => 'MS', 'name_fr' => 'Moyenne Section', 'name_en' => 'Middle Kindergarten', 'sous_system_id' => 5, 'school_id' => 2, 'ordre' => 4, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:46:19'],
            ['id' => 8, 'code' => 'NURSERY1', 'name_fr' => 'Nursery 1', 'name_en' => 'Nursery 1', 'sous_system_id' => 6, 'school_id' => 2, 'ordre' => 5, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:46:28'],
            ['id' => 9, 'code' => 'NURSERY2', 'name_fr' => 'Nursery 2', 'name_en' => 'Nursery 2', 'sous_system_id' => 6, 'school_id' => 2, 'ordre' => 6, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:46:56'],
            ['id' => 10, 'code' => 'PRENURSERY', 'name_fr' => 'Pré-Nursery', 'name_en' => 'Pre-Nursery', 'sous_system_id' => 6, 'school_id' => 2, 'ordre' => 7, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:46:49'],
            ['id' => 11, 'code' => 'PS', 'name_fr' => 'Petite Section', 'name_en' => 'Lower Kindergarten', 'sous_system_id' => 5, 'school_id' => 2, 'ordre' => 8, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:46:36'],
            ['id' => 12, 'code' => 'CP', 'name_fr' => 'Cours Préparatoire', 'name_en' => 'CP', 'sous_system_id' => 5, 'school_id' => 2, 'ordre' => 9, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 13, 'code' => 'CE1', 'name_fr' => 'Cours Élémentaire 1', 'name_en' => 'CE1', 'sous_system_id' => 5, 'school_id' => 2, 'ordre' => 10, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 14, 'code' => 'CE2', 'name_fr' => 'Cours Élémentaire 2', 'name_en' => 'CE2', 'sous_system_id' => 5, 'school_id' => 2, 'ordre' => 11, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 15, 'code' => 'CM1', 'name_fr' => 'Cours Moyen 1', 'name_en' => 'CM1', 'sous_system_id' => 5, 'school_id' => 2, 'ordre' => 12, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 16, 'code' => 'CM2', 'name_fr' => 'Cours Moyen 2', 'name_en' => 'CM2', 'sous_system_id' => 5, 'school_id' => 2, 'ordre' => 13, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 17, 'code' => 'SIL', 'name_fr' => 'SIL', 'name_en' => 'SIL', 'sous_system_id' => 5, 'school_id' => 2, 'ordre' => 14, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:31:51'],
            ['id' => 18, 'code' => 'CLASS1', 'name_fr' => 'Classe 1', 'name_en' => 'Class 1', 'sous_system_id' => 6, 'school_id' => 3, 'ordre' => 15, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 19, 'code' => 'CLASS2', 'name_fr' => 'Classe 2', 'name_en' => 'Class 2', 'sous_system_id' => 6, 'school_id' => 3, 'ordre' => 16, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 20, 'code' => 'CLASS3', 'name_fr' => 'Classe 3', 'name_en' => 'Class 3', 'sous_system_id' => 6, 'school_id' => 3, 'ordre' => 17, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 21, 'code' => 'CLASS4', 'name_fr' => 'Classe 4', 'name_en' => 'Class 4', 'sous_system_id' => 6, 'school_id' => 3, 'ordre' => 18, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 22, 'code' => 'CLASS5', 'name_fr' => 'Classe 5', 'name_en' => 'Class 5', 'sous_system_id' => 6, 'school_id' => 3, 'ordre' => 19, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 23, 'code' => 'CLASS6', 'name_fr' => 'Classe 6', 'name_en' => 'Class 6', 'sous_system_id' => 6, 'school_id' => 3, 'ordre' => 20, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 24, 'code' => 'ACC1', 'name_fr' => 'Comptabilité 1', 'name_en' => 'Accounting 1', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 21, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:53:57'],
            ['id' => 25, 'code' => 'ACC2', 'name_fr' => 'Comptabilité 2', 'name_en' => 'Accounting 2', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 22, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:53:57'],
            ['id' => 26, 'code' => 'ACC3', 'name_fr' => 'Comptabilité 3', 'name_en' => 'Accounting 3', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 23, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:53:57'],
            ['id' => 27, 'code' => 'ACC4', 'name_fr' => 'Comptabilité 4', 'name_en' => 'Accounting 4', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 24, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:53:57'],
            ['id' => 28, 'code' => 'ACC5', 'name_fr' => 'Comptabilité 5', 'name_en' => 'Accounting 5', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 25, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:53:57'],
            ['id' => 29, 'code' => 'ACC6', 'name_fr' => 'Comptabilité 6', 'name_en' => 'Accounting 6', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 26, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:53:57'],
            ['id' => 30, 'code' => 'AUTO1', 'name_fr' => 'Mécanique Auto 1', 'name_en' => 'Auto Mechanics 1', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 27, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:53:57'],
            ['id' => 31, 'code' => 'AUTO2', 'name_fr' => 'Mécanique Auto 2', 'name_en' => 'Auto Mechanics 2', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 28, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:53:57'],
            ['id' => 32, 'code' => 'AUTO3', 'name_fr' => 'Mécanique Auto 3', 'name_en' => 'Auto Mechanics 3', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 29, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:53:57'],
            ['id' => 33, 'code' => 'AUTO4', 'name_fr' => 'Mécanique Auto 4', 'name_en' => 'Auto Mechanics 4', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 30, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:53:57'],
            ['id' => 34, 'code' => 'AUTO5', 'name_fr' => 'Mécanique Auto 5', 'name_en' => 'Auto Mechanics 5', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 31, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 35, 'code' => 'AUTO6', 'name_fr' => 'Mécanique Auto 6', 'name_en' => 'Auto Mechanics 6', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 32, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 36, 'code' => 'AUTO7', 'name_fr' => 'Mécanique Auto 7', 'name_en' => 'Auto Mechanics 7', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 33, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 37, 'code' => 'BUILD4', 'name_fr' => 'Construction 4', 'name_en' => 'Building Construction 4', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 34, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 38, 'code' => 'BUILD5', 'name_fr' => 'Construction 5', 'name_en' => 'Building Construction 5', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 35, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 39, 'code' => 'BUILD6', 'name_fr' => 'Construction 6', 'name_en' => 'Building Construction 6', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 36, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 40, 'code' => 'BUILD7', 'name_fr' => 'Construction 7', 'name_en' => 'Building Construction 7', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 37, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 41, 'code' => 'BUILD8', 'name_fr' => 'Construction 8', 'name_en' => 'Building Construction 8', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 38, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 42, 'code' => 'BUILD9', 'name_fr' => 'Construction 9', 'name_en' => 'Building Construction 9', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 39, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 43, 'code' => 'BUILD10', 'name_fr' => 'Construction 10', 'name_en' => 'Building Construction 10', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 40, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 44, 'code' => 'CLOTH1', 'name_fr' => 'Habillement 1', 'name_en' => 'Clothing 1', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 41, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 45, 'code' => 'CLOTH2', 'name_fr' => 'Habillement 2', 'name_en' => 'Clothing 2', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 42, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 46, 'code' => 'CLOTH3', 'name_fr' => 'Habillement 3', 'name_en' => 'Clothing 3', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 43, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 47, 'code' => 'CLOTH4', 'name_fr' => 'Habillement 4', 'name_en' => 'Clothing 4', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 44, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 48, 'code' => 'CLOTH5', 'name_fr' => 'Habillement 5', 'name_en' => 'Clothing 5', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 45, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:21'],
            ['id' => 49, 'code' => 'CLOTH6', 'name_fr' => 'Habillement 6', 'name_en' => 'Clothing 6', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 46, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:43'],
            ['id' => 50, 'code' => 'CLOTH7', 'name_fr' => 'Habillement 7', 'name_en' => 'Clothing 7', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 47, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:43'],
            ['id' => 51, 'code' => 'ELEC1', 'name_fr' => 'Électricité 1', 'name_en' => 'Electricity 1', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 48, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:43'],
            ['id' => 52, 'code' => 'ELEC2', 'name_fr' => 'Électricité 2', 'name_en' => 'Electricity 2', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 49, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:43'],
            ['id' => 53, 'code' => 'ELEC3', 'name_fr' => 'Électricité 3', 'name_en' => 'Electricity 3', 'sous_system_id' => 6, 'school_id' => 1, 'ordre' => 50, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 02:54:43'],
            ['id' => 54, 'code' => 'ELEC4', 'name_fr' => 'Électricité 4', 'name_en' => 'Electricity 4', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 51, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 55, 'code' => 'ELEC5', 'name_fr' => 'Électricité 5', 'name_en' => 'Electricity 5', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 52, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 56, 'code' => 'ELEC6', 'name_fr' => 'Électricité 6', 'name_en' => 'Electricity 6', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 53, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 57, 'code' => 'ELEC7', 'name_fr' => 'Électricité 7', 'name_en' => 'Electricity 7', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 54, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 58, 'code' => 'HOME1', 'name_fr' => 'Économie Ménagère 1', 'name_en' => 'Home Economics 1', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 55, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 59, 'code' => 'HOME2', 'name_fr' => 'Économie Ménagère 2', 'name_en' => 'Home Economics 2', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 56, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 60, 'code' => 'HOME3', 'name_fr' => 'Économie Ménagère 3', 'name_en' => 'Home Economics 3', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 57, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 61, 'code' => 'HOME4', 'name_fr' => 'Économie Ménagère 4', 'name_en' => 'Home Economics 4', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 58, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 62, 'code' => 'HOME5', 'name_fr' => 'Économie Ménagère 5', 'name_en' => 'Home Economics 5', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 59, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 63, 'code' => 'HOME6', 'name_fr' => 'Économie Ménagère 6', 'name_en' => 'Home Economics 6', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 60, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 64, 'code' => 'HOME7', 'name_fr' => 'Économie Ménagère 7', 'name_en' => 'Home Economics 7', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 61, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 65, 'code' => 'MARKET1', 'name_fr' => 'Marketing 1', 'name_en' => 'Marketing 1', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 62, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 66, 'code' => 'MARKET2', 'name_fr' => 'Marketing 2', 'name_en' => 'Marketing 2', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 63, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 67, 'code' => 'MARKET3', 'name_fr' => 'Marketing 3', 'name_en' => 'Marketing 3', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 64, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 68, 'code' => 'MARKET4', 'name_fr' => 'Marketing 4', 'name_en' => 'Marketing 4', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 65, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 69, 'code' => 'MARKET5', 'name_fr' => 'Marketing 5', 'name_en' => 'Marketing 5', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 66, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 70, 'code' => 'MARKET6', 'name_fr' => 'Marketing 6', 'name_en' => 'Marketing 6', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 67, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 71, 'code' => 'MARKET7', 'name_fr' => 'Marketing 7', 'name_en' => 'Marketing 7', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 68, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 72, 'code' => 'ADMIN1', 'name_fr' => 'Technique Administrative et Communication 1', 'name_en' => 'Administrative and Communication Technique 1', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 69, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 73, 'code' => 'ADMIN2', 'name_fr' => 'Technique Administrative et Communication 2', 'name_en' => 'Administrative and Communication Technique 2', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 70, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 74, 'code' => 'ADMIN3', 'name_fr' => 'Technique Administrative et Communication 3', 'name_en' => 'Administrative and Communication Technique 3', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 71, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 75, 'code' => 'ADMIN4', 'name_fr' => 'Technique Administrative et Communication 4', 'name_en' => 'Administrative and Communication Technique 4', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 72, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 76, 'code' => 'ADMIN5', 'name_fr' => 'Technique Administrative et Communication 5', 'name_en' => 'Administrative and Communication Technique 5', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 73, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 77, 'code' => 'ADMIN6', 'name_fr' => 'Technique Administrative et Communication 6', 'name_en' => 'Administrative and Communication Technique 6', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 74, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
            ['id' => 78, 'code' => 'ADMIN7', 'name_fr' => 'Technique Administrative et Communication 7', 'name_en' => 'Administrative and Communication Technique 7', 'sous_system_id' => 9, 'school_id' => 1, 'ordre' => 75, 'created_at' => '2026-08-11 22:32:24', 'updated_at' => '2026-08-12 01:29:05'],
        ];
        DB::table($table)->insert($rows);

    }
}
