<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PalmaresExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows->values()->map(fn ($row, $i) => [
            $i + 1,
            $row['eleve']->nomComplet(),
            $row['eleve']->classe?->nom,
            number_format($row['moyenne'], 2),
        ]);
    }

    public function headings(): array
    {
        return ['#', 'Élève', 'Classe', 'Moyenne'];
    }
}
