<?php

namespace App\Services;

use App\Models\RapportRentreeTexte;

class RapportRentreeTexteService
{
    private const RUBRIQUES = [
        'securite_cloture', 'securite_detecteur_metaux', 'securite_controle_armes',
        'securite_surveillance_pauses', 'securite_autres_mesures',
        'probleme_infrastructure_maternelle', 'doleances',
        'problemes_fonctionnement', 'resolutions_conseil_maitres',
        'gouvernements_enfants', 'irr', 'evenements_socioculturels',
        'fetes_nationales', 'conclusion_generale',
    ];

    /**
     * Toutes les rubriques de texte libre pour l'année, chacune présente
     * même sans contenu saisi — l'écran affiche ainsi toujours la même
     * liste de champs plutôt que de la faire varier selon ce qui a déjà
     * été renseigné.
     *
     * @param  int|array<int>  $schoolId
     * @return array<string, string|null>
     */
    public function all(int|array $schoolId, int $anneeScolaireId): array
    {
        $existants = RapportRentreeTexte::forSchool($schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->pluck('contenu', 'rubrique');

        $resultat = [];
        foreach (self::RUBRIQUES as $rubrique) {
            $resultat[$rubrique] = $existants[$rubrique] ?? null;
        }

        return $resultat;
    }

    public function definir(int $schoolId, int $anneeScolaireId, string $rubrique, ?string $contenu): RapportRentreeTexte
    {
        return RapportRentreeTexte::updateOrCreate(
            ['school_id' => $schoolId, 'annee_scolaire_id' => $anneeScolaireId, 'rubrique' => $rubrique],
            ['contenu' => $contenu],
        );
    }
}
