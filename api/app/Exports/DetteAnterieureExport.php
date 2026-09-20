<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DetteAnterieureExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Collection $lignes) {}

    public function array(): array
    {
        return $this->lignes->map(fn(array $ligne) => [
            $ligne['eleve']['matricule'],
            $ligne['eleve']['nom_complet'],
            $ligne['reste'],
        ])->all();
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom', 'Dette'];
    }
}
