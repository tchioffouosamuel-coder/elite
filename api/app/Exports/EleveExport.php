<?php

namespace App\Exports;

use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EleveExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param int|array<int> $schoolId */
    public function __construct(private readonly int|array $schoolId, private readonly ?int $classeId = null) {}

    public function collection(): Collection
    {
        return Eleve::forSchool($this->schoolId)
            ->when($this->classeId, fn ($q, $id) => $q->where('classe_id', $id))
            ->with(['classe', 'tuteurs'])
            ->orderBy('nom_complet')
            ->get();
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom complet', 'Sexe', 'Date de naissance', 'Classe', 'Statut', 'Tuteur', 'Téléphone tuteur'];
    }

    public function map($eleve): array
    {
        $tuteur = $eleve->tuteurs->first();

        return [
            $eleve->matricule,
            $eleve->nom_complet,
            $eleve->sexe,
            $eleve->date_naissance?->format('Y-m-d'),
            $eleve->classe?->nom,
            $eleve->statut,
            $tuteur?->nom_complet,
            $tuteur?->telephone,
        ];
    }
}
