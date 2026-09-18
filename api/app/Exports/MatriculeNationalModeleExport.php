<?php

namespace App\Exports;

use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Modèle « à remplir » : un élève sans matricule national par ligne, déjà
 * identifié (matricule interne, nom, classe) — sans quoi quiconque remplit
 * le fichier n'aurait aucun moyen de savoir à quel élève chaque matricule
 * doit revenir. Colonne `Matricule national` volontairement vide, à
 * compléter puis réimporter via {@see \App\Imports\MatriculeNationalImport}.
 */
class MatriculeNationalModeleExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param int|array<int> $schoolId */
    public function __construct(private readonly int|array $schoolId) {}

    public function collection(): Collection
    {
        return Eleve::forSchool($this->schoolId)
            ->whereHas('school', fn ($q) => $q->where('type', 'secondaire'))
            ->where('statut', 'actif')
            ->whereNull('matricule_national')
            ->with(['classe:id,nom', 'school:id,name'])
            ->orderBy('nom_complet')
            ->get();
    }

    public function headings(): array
    {
        return ['IDEleves', 'Nom complet', 'Classe', 'École', 'Matricule national'];
    }

    public function map($eleve): array
    {
        return [
            $eleve->matricule,
            $eleve->nom_complet,
            $eleve->classe?->nom,
            $eleve->school?->name,
            null,
        ];
    }
}
