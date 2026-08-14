<?php

namespace App\Exports;

use App\Models\Personnel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PersonnelExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly int $schoolId) {}

    public function collection(): Collection
    {
        return Personnel::forSchool($this->schoolId)->with('departement')->orderBy('nom_complet')->get();
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom complet', 'Fonction', 'Département', 'Téléphone', 'Email', 'Statut'];
    }

    public function map($personnel): array
    {
        return [
            $personnel->matricule,
            $personnel->nom_complet,
            $personnel->fonction,
            $personnel->departement?->nom,
            $personnel->telephone,
            $personnel->email,
            $personnel->statut,
        ];
    }
}
