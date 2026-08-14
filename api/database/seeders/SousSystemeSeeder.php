<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SousSysteme;
use Illuminate\Database\Seeder;

class SousSystemeSeeder extends Seeder
{
    public function run(): void
    {
        $schools = School::all();

        foreach ($schools as $school) {
            // Créer les sous-systèmes par défaut pour chaque établissement
            SousSysteme::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'FR'],
                [
                    'nom' => 'Francophone',
                    'description' => 'Enseignement en français',
                ]
            );

            SousSysteme::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'EN'],
                [
                    'nom' => 'Anglophone',
                    'description' => 'Enseignement en anglais',
                ]
            );

            SousSysteme::firstOrCreate(
                ['school_id' => $school->id, 'code' => 'BI'],
                [
                    'nom' => 'Bilingue',
                    'description' => 'Enseignement bilingue (français et anglais)',
                ]
            );
        }
    }
}
