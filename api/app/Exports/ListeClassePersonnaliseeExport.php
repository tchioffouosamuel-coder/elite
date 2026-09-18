<?php

namespace App\Exports;

use App\Support\ListeClasseColonnes;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Export Excel de la « liste personnalisée de classe » — colonnes dynamiques, cf. ListeClassePersonnaliseeService. */
class ListeClassePersonnaliseeExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $colonnes
     * @param  list<array<string, string>>  $lignes
     */
    public function __construct(
        private readonly array $colonnes,
        private readonly array $lignes,
        private readonly string $titreFr,
    ) {}

    public function array(): array
    {
        return array_map(
            fn (array $ligne) => array_map(fn (string $colonne) => $ligne[$colonne] ?? '', $this->colonnes),
            $this->lignes,
        );
    }

    public function headings(): array
    {
        return array_map(fn (string $colonne) => ListeClasseColonnes::libelle($colonne), $this->colonnes);
    }

    public function title(): string
    {
        return mb_substr($this->titreFr, 0, 31) ?: 'Liste';
    }
}
