<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export (et modèle vide, selon ce que {@see \App\Services\NotePrimaireService::lignesExport()}
 * a produit) des notes d'une compétence pour tout le trimestre — colonnes
 * alignées avec {@see \App\Imports\NotePrimaireImport} (matricule, nom
 * complet, puis une colonne « {Volet} S{ordre} » par volet × séquence) pour
 * un aller-retour sans adaptation côté enseignant.
 */
class NotePrimaireExport implements FromCollection, WithHeadings
{
    /**
     * @param  Collection<int, array{matricule: ?string, nom_complet: string, cellules: array<string, mixed>}>  $lignes
     * @param  list<string>  $entetesCellules  ordre des colonnes « {Volet} S{ordre} », cf. lignesExport()
     */
    public function __construct(
        private readonly Collection $lignes,
        private readonly array $entetesCellules,
    ) {}

    public function headings(): array
    {
        return ['Matricule', 'Nom complet', ...$this->entetesCellules];
    }

    public function collection(): Collection
    {
        return $this->lignes->map(fn (array $l) => [
            $l['matricule'],
            $l['nom_complet'],
            ...array_map(fn (string $cle) => $l['cellules'][$cle] ?? null, $this->entetesCellules),
        ]);
    }
}
