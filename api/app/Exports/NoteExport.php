<?php

namespace App\Exports;

use App\Imports\NoteImport;
use App\Services\NoteService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export (et modèle vide, selon ce que {@see NoteService::lignesExport()}
 * a produit) des notes d'une affectation classe↔matière pour une séquence —
 * colonnes alignées avec {@see NoteImport} (matricule, note)
 * pour un aller-retour sans adaptation côté enseignant.
 */
class NoteExport implements FromCollection, WithHeadings
{
    /** @param  Collection<int, array{matricule: ?string, nom_complet:string, note: ?float}>  $lignes */
    public function __construct(private readonly Collection $lignes) {}

    public function headings(): array
    {
        return ['Matricule', 'Nom complet', 'Note'];
    }

    public function collection(): Collection
    {
        return $this->lignes->map(fn (array $l) => [$l['matricule'], $l['nom_complet'], $l['note']]);
    }
}
