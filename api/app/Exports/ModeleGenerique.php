<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Classeur vierge (en-têtes seuls) pour le bouton « Télécharger le modèle » —
 * piloté par une simple liste de libellés, réutilisable aussi bien par une
 * {@see \App\Support\ImportExport\SpecificationModele} que par une classe
 * `Imports\Xxx` existante (via sa méthode statique `enTetes()`).
 */
class ModeleGenerique implements FromArray, WithHeadings
{
    /** @param list<string> $enTetes */
    public function __construct(private readonly array $enTetes) {}

    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return $this->enTetes;
    }
}
