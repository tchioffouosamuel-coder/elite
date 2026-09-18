<?php

namespace App\Exports;

use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Liste complète des matricules nationaux (secondaire uniquement), renseignés ou non. */
class MatriculeNationalExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param int|array<int> $schoolId */
    public function __construct(private readonly int|array $schoolId) {}

    public function collection(): Collection
    {
        return Eleve::forSchool($this->schoolId)
            ->whereHas('school', fn ($q) => $q->where('type', 'secondaire'))
            ->where('statut', 'actif')
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
            $eleve->matricule_national,
        ];
    }
}
