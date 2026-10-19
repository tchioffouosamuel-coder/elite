<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class NotesEnseignantExport implements WithMultipleSheets
{
    /** @param list<NotesEnseignantSheet> $feuilles */
    public function __construct(private readonly array $feuilles) {}

    public function sheets(): array
    {
        return $this->feuilles;
    }
}
