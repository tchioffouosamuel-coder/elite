<?php

namespace Database\Seeders;

use App\Models\Niveau;
use Illuminate\Database\Seeder;

class NiveauSeeder extends Seeder
{
    public function run(): void
    {
        $niveaux = [
            ['code' => 'maternelle', 'name_fr' => 'Maternelle', 'name_en' => 'Kindergarten', 'ordre' => 1],
            ['code' => 'primaire', 'name_fr' => 'Primaire', 'name_en' => 'Primary', 'ordre' => 2],
            ['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'Secondary', 'ordre' => 3],
        ];

        foreach ($niveaux as $niveau) {
            Niveau::updateOrCreate(['code' => $niveau['code']], $niveau);
        }
    }
}
