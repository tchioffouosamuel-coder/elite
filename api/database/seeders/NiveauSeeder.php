<?php

namespace Database\Seeders;

use App\Models\Niveau;
use Illuminate\Database\Seeder;

class NiveauSeeder extends Seeder
{
    public function run(): void
    {
        $niveaux = [
            // Maternelle
            ['label' => 'BABY SECTION', 'code' => 'BABY', 'name_fr' => 'Baby Section', 'name_en' => 'Baby Section'],
            ['label' => 'BEBE SECTION', 'code' => 'BEBE', 'name_fr' => 'Bébé Section', 'name_en' => 'Baby Section'],
            ['label' => 'GRANDE SECTION', 'code' => 'GS', 'name_fr' => 'Grande Section', 'name_en' => 'Upper Kindergarten'],
            ['label' => 'MOYENNE SECTION', 'code' => 'MS', 'name_fr' => 'Moyenne Section', 'name_en' => 'Middle Kindergarten'],
            ['label' => 'NURSERY1', 'code' => 'NURSERY1', 'name_fr' => 'Nursery 1', 'name_en' => 'Nursery 1'],
            ['label' => 'NURSERY 2', 'code' => 'NURSERY2', 'name_fr' => 'Nursery 2', 'name_en' => 'Nursery 2'],
            ['label' => 'PRE-NURSERY', 'code' => 'PRENURSERY', 'name_fr' => 'Pré-Nursery', 'name_en' => 'Pre-Nursery'],
            ['label' => 'Petite Section', 'code' => 'PS', 'name_fr' => 'Petite Section', 'name_en' => 'Lower Kindergarten'],

            // Primaire
            ['label' => 'CP', 'code' => 'CP', 'name_fr' => 'Cours Préparatoire', 'name_en' => 'CP'],
            ['label' => 'CE1', 'code' => 'CE1', 'name_fr' => 'Cours Élémentaire 1', 'name_en' => 'CE1'],
            ['label' => 'CE2', 'code' => 'CE2', 'name_fr' => 'Cours Élémentaire 2', 'name_en' => 'CE2'],
            ['label' => 'CM1', 'code' => 'CM1', 'name_fr' => 'Cours Moyen 1', 'name_en' => 'CM1'],
            ['label' => 'CM2', 'code' => 'CM2', 'name_fr' => 'Cours Moyen 2', 'name_en' => 'CM2'],
            ['label' => 'SIL', 'code' => 'SIL', 'name_fr' => 'Sixième Initiale Libre', 'name_en' => 'SIL'],

            // Collège
            ['label' => 'CLASS 1', 'code' => 'CLASS1', 'name_fr' => 'Classe 1', 'name_en' => 'Class 1'],
            ['label' => 'CLASS 2', 'code' => 'CLASS2', 'name_fr' => 'Classe 2', 'name_en' => 'Class 2'],
            ['label' => 'CLASS 3', 'code' => 'CLASS3', 'name_fr' => 'Classe 3', 'name_en' => 'Class 3'],
            ['label' => 'CLASS 4', 'code' => 'CLASS4', 'name_fr' => 'Classe 4', 'name_en' => 'Class 4'],
            ['label' => 'CLASS 5', 'code' => 'CLASS5', 'name_fr' => 'Classe 5', 'name_en' => 'Class 5'],
            ['label' => 'CLASS 6', 'code' => 'CLASS6', 'name_fr' => 'Classe 6', 'name_en' => 'Class 6'],

            // Technique - Comptabilité
            ['label' => 'ACCOUNTING 1', 'code' => 'ACC1', 'name_fr' => 'Comptabilité 1', 'name_en' => 'Accounting 1'],
            ['label' => 'ACCOUNTING 2', 'code' => 'ACC2', 'name_fr' => 'Comptabilité 2', 'name_en' => 'Accounting 2'],
            ['label' => 'ACCOUNTING 3', 'code' => 'ACC3', 'name_fr' => 'Comptabilité 3', 'name_en' => 'Accounting 3'],
            ['label' => 'ACCOUNTING 4', 'code' => 'ACC4', 'name_fr' => 'Comptabilité 4', 'name_en' => 'Accounting 4'],
            ['label' => 'ACCOUNTING 5', 'code' => 'ACC5', 'name_fr' => 'Comptabilité 5', 'name_en' => 'Accounting 5'],
            ['label' => 'ACCOUNTING 6', 'code' => 'ACC6', 'name_fr' => 'Comptabilité 6', 'name_en' => 'Accounting 6'],

            // Technique - Auto Mécanique
            ['label' => 'AUTO MECHANICS 1', 'code' => 'AUTO1', 'name_fr' => 'Mécanique Auto 1', 'name_en' => 'Auto Mechanics 1'],
            ['label' => 'AUTO MECHANICS 2', 'code' => 'AUTO2', 'name_fr' => 'Mécanique Auto 2', 'name_en' => 'Auto Mechanics 2'],
            ['label' => 'AUTO MECHANICS 3', 'code' => 'AUTO3', 'name_fr' => 'Mécanique Auto 3', 'name_en' => 'Auto Mechanics 3'],
            ['label' => 'AUTO MECHANICS 4', 'code' => 'AUTO4', 'name_fr' => 'Mécanique Auto 4', 'name_en' => 'Auto Mechanics 4'],
            ['label' => 'AUTO MECHANICS 5', 'code' => 'AUTO5', 'name_fr' => 'Mécanique Auto 5', 'name_en' => 'Auto Mechanics 5'],
            ['label' => 'AUTO MECHANICS 6', 'code' => 'AUTO6', 'name_fr' => 'Mécanique Auto 6', 'name_en' => 'Auto Mechanics 6'],
            ['label' => 'AUTO MECHANICS 7', 'code' => 'AUTO7', 'name_fr' => 'Mécanique Auto 7', 'name_en' => 'Auto Mechanics 7'],

            // Technique - Construction
            ['label' => 'BUILDING CONSTRUCTION 4', 'code' => 'BUILD4', 'name_fr' => 'Construction 4', 'name_en' => 'Building Construction 4'],
            ['label' => 'BUILDING CONSTRUCTION 5', 'code' => 'BUILD5', 'name_fr' => 'Construction 5', 'name_en' => 'Building Construction 5'],
            ['label' => 'BUILDING CONSTRUCTION 6', 'code' => 'BUILD6', 'name_fr' => 'Construction 6', 'name_en' => 'Building Construction 6'],
            ['label' => 'BUILDING CONSTRUCTION 7', 'code' => 'BUILD7', 'name_fr' => 'Construction 7', 'name_en' => 'Building Construction 7'],
            ['label' => 'BUILDING CONSTRUCTION 8', 'code' => 'BUILD8', 'name_fr' => 'Construction 8', 'name_en' => 'Building Construction 8'],
            ['label' => 'BUILDING CONSTRUCTION 9', 'code' => 'BUILD9', 'name_fr' => 'Construction 9', 'name_en' => 'Building Construction 9'],
            ['label' => 'BUILDING CONSTRUCTION 10', 'code' => 'BUILD10', 'name_fr' => 'Construction 10', 'name_en' => 'Building Construction 10'],

            // Technique - Habillement
            ['label' => 'CLOTHING 1', 'code' => 'CLOTH1', 'name_fr' => 'Habillement 1', 'name_en' => 'Clothing 1'],
            ['label' => 'CLOTHING 2', 'code' => 'CLOTH2', 'name_fr' => 'Habillement 2', 'name_en' => 'Clothing 2'],
            ['label' => 'CLOTHING 3', 'code' => 'CLOTH3', 'name_fr' => 'Habillement 3', 'name_en' => 'Clothing 3'],
            ['label' => 'CLOTHING 4', 'code' => 'CLOTH4', 'name_fr' => 'Habillement 4', 'name_en' => 'Clothing 4'],
            ['label' => 'CLOTHING 5', 'code' => 'CLOTH5', 'name_fr' => 'Habillement 5', 'name_en' => 'Clothing 5'],
            ['label' => 'CLOTHING 6', 'code' => 'CLOTH6', 'name_fr' => 'Habillement 6', 'name_en' => 'Clothing 6'],
            ['label' => 'CLOTHING 7', 'code' => 'CLOTH7', 'name_fr' => 'Habillement 7', 'name_en' => 'Clothing 7'],

            // Technique - Électricité
            ['label' => 'ELECTRICITY 1', 'code' => 'ELEC1', 'name_fr' => 'Électricité 1', 'name_en' => 'Electricity 1'],
            ['label' => 'ELECTRICITY 2', 'code' => 'ELEC2', 'name_fr' => 'Électricité 2', 'name_en' => 'Electricity 2'],
            ['label' => 'ELECTRICITY 3', 'code' => 'ELEC3', 'name_fr' => 'Électricité 3', 'name_en' => 'Electricity 3'],
            ['label' => 'ELECTRICITY 4', 'code' => 'ELEC4', 'name_fr' => 'Électricité 4', 'name_en' => 'Electricity 4'],
            ['label' => 'ELECTRICITY 5', 'code' => 'ELEC5', 'name_fr' => 'Électricité 5', 'name_en' => 'Electricity 5'],
            ['label' => 'ELECTRICITY 6', 'code' => 'ELEC6', 'name_fr' => 'Électricité 6', 'name_en' => 'Electricity 6'],
            ['label' => 'ELECTRICITY 7', 'code' => 'ELEC7', 'name_fr' => 'Électricité 7', 'name_en' => 'Electricity 7'],

            // Technique - Économie Ménagère
            ['label' => 'HOME ECONOMICS 1', 'code' => 'HOME1', 'name_fr' => 'Économie Ménagère 1', 'name_en' => 'Home Economics 1'],
            ['label' => 'HOME ECONOMICS 2', 'code' => 'HOME2', 'name_fr' => 'Économie Ménagère 2', 'name_en' => 'Home Economics 2'],
            ['label' => 'HOME ECONOMICS 3', 'code' => 'HOME3', 'name_fr' => 'Économie Ménagère 3', 'name_en' => 'Home Economics 3'],
            ['label' => 'HOME ECONOMICS 4', 'code' => 'HOME4', 'name_fr' => 'Économie Ménagère 4', 'name_en' => 'Home Economics 4'],
            ['label' => 'HOME ECONOMICS 5', 'code' => 'HOME5', 'name_fr' => 'Économie Ménagère 5', 'name_en' => 'Home Economics 5'],
            ['label' => 'HOME ECONOMICS 6', 'code' => 'HOME6', 'name_fr' => 'Économie Ménagère 6', 'name_en' => 'Home Economics 6'],
            ['label' => 'HOME ECONOMICS 7', 'code' => 'HOME7', 'name_fr' => 'Économie Ménagère 7', 'name_en' => 'Home Economics 7'],

            // Technique - Marketing
            ['label' => 'MARKETING 1', 'code' => 'MARKET1', 'name_fr' => 'Marketing 1', 'name_en' => 'Marketing 1'],
            ['label' => 'MARKETING 2', 'code' => 'MARKET2', 'name_fr' => 'Marketing 2', 'name_en' => 'Marketing 2'],
            ['label' => 'MARKETING 3', 'code' => 'MARKET3', 'name_fr' => 'Marketing 3', 'name_en' => 'Marketing 3'],
            ['label' => 'MARKETING 4', 'code' => 'MARKET4', 'name_fr' => 'Marketing 4', 'name_en' => 'Marketing 4'],
            ['label' => 'MARKETING 5', 'code' => 'MARKET5', 'name_fr' => 'Marketing 5', 'name_en' => 'Marketing 5'],
            ['label' => 'MARKETING 6', 'code' => 'MARKET6', 'name_fr' => 'Marketing 6', 'name_en' => 'Marketing 6'],
            ['label' => 'MARKETING 7', 'code' => 'MARKET7', 'name_fr' => 'Marketing 7', 'name_en' => 'Marketing 7'],

            // Technique - Administrative et Communication
            ['label' => 'ADMINISTRATIVE AND COMMUNICATION TECHNIQUE 1', 'code' => 'ADMIN1', 'name_fr' => 'Technique Administrative et Communication 1', 'name_en' => 'Administrative and Communication Technique 1'],
            ['label' => 'ADMINISTRATIVE AND COMMUNICATION TECHNIQUE 2', 'code' => 'ADMIN2', 'name_fr' => 'Technique Administrative et Communication 2', 'name_en' => 'Administrative and Communication Technique 2'],
            ['label' => 'ADMINISTRATIVE AND COMMUNICATION TECHNIQUE 3', 'code' => 'ADMIN3', 'name_fr' => 'Technique Administrative et Communication 3', 'name_en' => 'Administrative and Communication Technique 3'],
            ['label' => 'ADMINISTRATIVE AND COMMUNICATION TECHNIQUE 4', 'code' => 'ADMIN4', 'name_fr' => 'Technique Administrative et Communication 4', 'name_en' => 'Administrative and Communication Technique 4'],
            ['label' => 'ADMINISTRATIVE AND COMMUNICATION TECHNIQUE 5', 'code' => 'ADMIN5', 'name_fr' => 'Technique Administrative et Communication 5', 'name_en' => 'Administrative and Communication Technique 5'],
            ['label' => 'ADMINISTRATIVE AND COMMUNICATION TECHNIQUE 6', 'code' => 'ADMIN6', 'name_fr' => 'Technique Administrative et Communication 6', 'name_en' => 'Administrative and Communication Technique 6'],
            ['label' => 'ADMINISTRATIVE AND COMMUNICATION TECHNIQUE 7', 'code' => 'ADMIN7', 'name_fr' => 'Technique Administrative et Communication 7', 'name_en' => 'Administrative and Communication Technique 7'],
        ];

        foreach ($niveaux as $index => $niveau) {
            // Déterminer school_id en fonction du groupe de niveaux
            // 0-13: maternelle (school_id = 2)
            // 14-37: primaire (school_id = 3)
            // 38+: secondaire (school_id = 1)
            if ($index < 14) {
                $schoolId = 2; // Nursery
            } elseif ($index < 38) {
                $schoolId = 3; // Primary
            } else {
                $schoolId = 1; // Technical and Commercial
            }

            Niveau::updateOrCreate(
                ['code' => $niveau['code']],
                [
                    'label' => $niveau['label'],
                    'name_fr' => $niveau['name_fr'],
                    'name_en' => $niveau['name_en'],
                    'school_id' => $schoolId,
                    'ordre' => $index + 1,
                ]
            );
        }
    }
}
