<?php

namespace App\Exports;

use App\Services\PreinscriptionService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AnciensReinscritsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param int|array<int> $schoolId */
    public function __construct(private readonly int|array $schoolId, private readonly PreinscriptionService $service) {}

    public function collection(): Collection
    {
        return $this->service->listeAnciensReinscrits($this->schoolId)
            ->load(['school', 'classe.niveau', 'tuteurs'])
            ->sortBy(fn($eleve) => mb_strtolower($eleve->nom_complet))
            ->values();
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom complet', 'École', 'Classe', 'Sexe', 'Tuteur', 'Téléphone tuteur', 'Statut'];
    }

    public function map($eleve): array
    {
        return [
            $eleve->matricule,
            $eleve->nom_complet,
            $eleve->school?->name,
            $eleve->classe?->nom,
            $eleve->sexe === 'F' ? 'Fille' : 'Garçon',
            $eleve->tuteurs->first()?->nom_complet,
            $eleve->tuteurs->first()?->telephone,
            'Réinscrit',
        ];
    }
}