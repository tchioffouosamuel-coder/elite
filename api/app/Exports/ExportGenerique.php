<?php

namespace App\Exports;

use App\Support\ImportExport\SpecificationModele;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Export mécanique pour tout modèle décrit par une {@see SpecificationModele}. */
class ExportGenerique implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly SpecificationModele $spec,
        private readonly int|array $schoolId,
    ) {}

    public function collection(): Collection
    {
        return $this->spec->pourExport($this->schoolId)->get();
    }

    public function headings(): array
    {
        return array_values($this->spec->libellesTemplate());
    }

    public function map($ligne): array
    {
        return array_map(
            fn (string $cle) => $this->spec->valeurExport($ligne, $cle),
            array_keys($this->spec->libellesTemplate()),
        );
    }
}
