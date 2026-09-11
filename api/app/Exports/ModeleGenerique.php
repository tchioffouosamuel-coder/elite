<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Classeur vierge (en-têtes seuls) pour le bouton « Télécharger le modèle » —
 * piloté par une simple liste de libellés, réutilisable aussi bien par une
 * {@see \App\Support\ImportExport\SpecificationModele} que par une classe
 * `Imports\Xxx` existante (via sa méthode statique `enTetes()`).
 */
class ModeleGenerique implements FromArray, WithCustomStartCell, WithHeadings
{
    /**
     * @param  list<string>  $enTetes
     * @param  int  $ligneEnTetes  Ligne (1-indexée) où l'import correspondant attend les
     *                             en-têtes — cf. `WithHeadingRow::headingRow()` de la classe
     *                             `Imports\Xxx`. Les lignes qui la précèdent restent vides,
     *                             faute de quoi le modèle téléchargé ne se réimporte pas.
     */
    public function __construct(
        private readonly array $enTetes,
        private readonly int $ligneEnTetes = 1,
    ) {}

    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return $this->enTetes;
    }

    public function startCell(): string
    {
        return 'A' . $this->ligneEnTetes;
    }
}
