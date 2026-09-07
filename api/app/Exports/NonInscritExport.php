<?php

namespace App\Exports;

use App\Models\Eleve;
use App\Services\PreinscriptionService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Anciens élèves qui ne se sont pas encore réinscrits pour l'année scolaire active. */
class NonInscritExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param int|array<int> $schoolId */
    public function __construct(private readonly int|array $schoolId, private readonly PreinscriptionService $service) {}

    public function collection(): Collection
    {
        return $this->service->listeAnciensNonReinscrits($this->schoolId)->load(['classe', 'tuteurs']);
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom complet', 'Dernière classe', 'Tuteur', 'Téléphone tuteur'];
    }

    public function map($eleve): array
    {
        /** @var Eleve $eleve */
        $tuteur = $eleve->tuteurs->first();

        return [
            $eleve->matricule,
            $eleve->nom_complet,
            $eleve->classe?->nom,
            $tuteur?->nom_complet,
            $tuteur?->telephone,
        ];
    }
}
