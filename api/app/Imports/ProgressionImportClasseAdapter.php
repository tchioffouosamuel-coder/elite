<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Distribue chaque feuille d'un classeur d'import groupé vers le
 * `ProgressionImport` de la matière qui lui correspond — construit par
 * `ProgressionController::importClasse()` à partir du préfixe numérique du
 * titre de chaque feuille. Une feuille absente du tableau (matière non
 * reconnue, hors périmètre…) est simplement ignorée par maatwebsite : elle
 * n'a pas besoin d'être filtrée ici.
 */
class ProgressionImportClasseAdapter implements WithMultipleSheets
{
    /** @param  array<int, ProgressionImport>  $imports  indexés par position de feuille (0-based) */
    public function __construct(private readonly array $imports) {}

    public function sheets(): array
    {
        return $this->imports;
    }
}
