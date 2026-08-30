<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Modèle d'import des rémunérations : deux feuilles, « Liste » (les noms du
 * personnel en poste) et « Import » (la saisie proprement dite), la colonne
 * Nom de la seconde étant une liste déroulante alimentée par la première —
 * ainsi impossible de désigner un agent qui n'existe pas ou de fautes de
 * frappe sur un nom déjà en base.
 */
class RemunerationTemplateExport implements WithMultipleSheets
{
    /** @param  Collection<int, string>  $nomsPersonnel */
    public function __construct(private readonly Collection $nomsPersonnel) {}

    public function sheets(): array
    {
        return [
            new RemunerationTemplateListeSheet($this->nomsPersonnel),
            new RemunerationTemplateImportSheet($this->nomsPersonnel->count()),
        ];
    }
}
