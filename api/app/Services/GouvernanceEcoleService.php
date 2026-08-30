<?php

namespace App\Services;

use App\Models\Apee;
use App\Models\ConseilEcole;

/**
 * Conseil d'école et APEE (tableaux 29-30 du rapport de rentrée MINEDUB) :
 * une fiche unique par école et par année scolaire, créée à la demande dès
 * la première consultation plutôt qu'au démarrage de l'année — la plupart
 * des écoles ne la renseigneront jamais.
 */
class GouvernanceEcoleService
{
    public function conseilEcole(int $schoolId, int $anneeScolaireId): ConseilEcole
    {
        return ConseilEcole::firstOrNew(['school_id' => $schoolId, 'annee_scolaire_id' => $anneeScolaireId]);
    }

    public function definirConseilEcole(int $schoolId, int $anneeScolaireId, array $attributes): ConseilEcole
    {
        return ConseilEcole::updateOrCreate(
            ['school_id' => $schoolId, 'annee_scolaire_id' => $anneeScolaireId],
            $attributes,
        );
    }

    public function apee(int $schoolId, int $anneeScolaireId): Apee
    {
        return Apee::firstOrNew(['school_id' => $schoolId, 'annee_scolaire_id' => $anneeScolaireId]);
    }

    public function definirApee(int $schoolId, int $anneeScolaireId, array $attributes): Apee
    {
        return Apee::updateOrCreate(
            ['school_id' => $schoolId, 'annee_scolaire_id' => $anneeScolaireId],
            $attributes,
        );
    }
}
