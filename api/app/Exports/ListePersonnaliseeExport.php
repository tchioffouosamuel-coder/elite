<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Export Excel générique pour les listes personnalisées à colonnes dynamiques. */
class ListePersonnaliseeExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $colonnes
     * @param  list<array<string, string>>  $lignes
     * @param  array<string, string>  $entetes
     */
    public function __construct(
        private readonly array $colonnes,
        private readonly array $lignes,
        private readonly array $entetes,
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
        return array_map(fn (string $colonne) => $this->entetes[$colonne] ?? $colonne, $this->colonnes);
    }

    public function title(): string
    {
        return mb_substr($this->titreFr, 0, 31) ?: 'Liste';
    }
}
