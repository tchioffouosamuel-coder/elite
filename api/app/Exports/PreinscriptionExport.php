<?php

namespace App\Exports;

use App\Models\Preinscription;
use App\Services\PreinscriptionService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Préinscriptions validées pour l'année scolaire active — la campagne de réinscription en cours. */
class PreinscriptionExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param int|array<int> $schoolId */
    public function __construct(private readonly int|array $schoolId, private readonly PreinscriptionService $service) {}

    public function collection(): Collection
    {
        return $this->service->listeInscritsAnneeActive($this->schoolId)->load(['eleve', 'tuteur']);
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom complet', 'Type', 'Tuteur', 'Téléphone tuteur', 'Validée le'];
    }

    public function map($preinscription): array
    {
        /** @var Preinscription $preinscription */
        return [
            $preinscription->eleve?->matricule,
            $preinscription->eleve?->nom_complet ?? $preinscription->donnees_eleve['nom_complet'] ?? null,
            $preinscription->type === 'nouveau' ? 'Nouvel élève' : 'Ancien élève',
            $preinscription->tuteur?->nom_complet,
            $preinscription->tuteur?->telephone,
            $preinscription->traite_le?->format('Y-m-d'),
        ];
    }
}
