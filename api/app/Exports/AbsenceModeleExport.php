<?php

namespace App\Exports;

use App\Imports\AbsenceImport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Modèle vide de correction d'assiduité pour une classe du secondaire —
 * matricule et nom déjà remplis, heures à saisir — colonnes alignées avec
 * {@see AbsenceImport} pour un aller-retour sans adaptation.
 */
class AbsenceModeleExport implements FromCollection, WithHeadings
{
    /** @param  Collection<int, array{matricule: ?string, nom_complet:string}>  $lignes */
    public function __construct(private readonly Collection $lignes) {}

    public function headings(): array
    {
        return ['Matricule', 'Nom complet', 'Heures justifiées', 'Heures non justifiées'];
    }

    public function collection(): Collection
    {
        return $this->lignes->map(fn (array $l) => [$l['matricule'], $l['nom_complet'], null, null]);
    }
}
