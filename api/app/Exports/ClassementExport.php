<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Reçoit les lignes déjà calculées par MoyenneService (pas de recalcul ici :
 * une seule source de vérité pour les moyennes/rangs, cf. MoyenneService).
 */
class ClassementExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows->map(fn ($row) => [
            $row['rang'] ?? '—',
            $row['nom_complet'],
            $row['moyenne'] !== null ? number_format($row['moyenne'], 2) : '—',
            $row['cote'],
        ]);
    }

    public function headings(): array
    {
        return ['Rang', 'Élève', 'Moyenne', 'Cote'];
    }
}
