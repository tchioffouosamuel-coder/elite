<?php

namespace App\Services;

use App\Models\RapportTrimestreTexte;

class RapportTrimestreTexteService
{
    private const RUBRIQUES = [
        'introduction', 'observations_structure', 'observations_eleves',
        'observations_personnel', 'difficultes_rencontrees', 'conclusion_generale',
    ];

    /**
     * Toutes les rubriques de texte libre pour le trimestre, chacune présente
     * même sans contenu saisi — même principe que
     * `RapportRentreeTexteService::all()`.
     *
     * @param  int|array<int>  $schoolId
     * @return array<string, string|null>
     */
    public function all(int|array $schoolId, int $trimestreId): array
    {
        $existants = RapportTrimestreTexte::forSchool($schoolId)
            ->where('trimestre_id', $trimestreId)
            ->pluck('contenu', 'rubrique');

        $resultat = [];
        foreach (self::RUBRIQUES as $rubrique) {
            $resultat[$rubrique] = $existants[$rubrique] ?? null;
        }

        return $resultat;
    }

    public function definir(int $schoolId, int $trimestreId, string $rubrique, ?string $contenu): RapportTrimestreTexte
    {
        return RapportTrimestreTexte::updateOrCreate(
            ['school_id' => $schoolId, 'trimestre_id' => $trimestreId, 'rubrique' => $rubrique],
            ['contenu' => $contenu],
        );
    }
}
