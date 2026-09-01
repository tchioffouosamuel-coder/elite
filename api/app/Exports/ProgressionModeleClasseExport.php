<?php

namespace App\Exports;

use App\Models\ClasseMatiere;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Modèle d'import groupé de la fiche de progression pour toute une classe :
 * une feuille par matière affectée, prête à remplir puis réimporter en un
 * seul envoi (cf. ProgressionController::importClasse()) plutôt que
 * matière par matière.
 */
class ProgressionModeleClasseExport implements WithMultipleSheets
{
    /** @param  Collection<int, ClasseMatiere>  $affectations */
    public function __construct(
        private readonly Collection $affectations,
        private readonly string $cycle,
        private readonly ?string $anneeScolaire,
    ) {}

    public function sheets(): array
    {
        return $this->affectations
            ->map(fn (ClasseMatiere $cm) => new ProgressionModeleMatiereSheet($cm, $this->cycle, $this->anneeScolaire))
            ->all();
    }
}
