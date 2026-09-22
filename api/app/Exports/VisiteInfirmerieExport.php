<?php

namespace App\Exports;

use App\Imports\VisiteInfirmerieImport;
use App\Models\VisiteInfirmerie;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VisiteInfirmerieExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly int|array $schoolId) {}

    public function collection(): Collection
    {
        return VisiteInfirmerie::forSchool($this->schoolId)
            ->with(['eleve', 'malaises', 'materiels'])
            ->latest('date_visite')
            ->get();
    }

    public function headings(): array
    {
        return VisiteInfirmerieImport::enTetes();
    }

    public function map($visite): array
    {
        return [
            $visite->id,
            $visite->eleve?->matricule,
            $visite->eleve?->nom_complet,
            $visite->date_visite?->format('Y-m-d H:i'),
            $visite->raison,
            $visite->malaises->pluck('label_fr')->implode('; '),
            $visite->soins_prodiges,
            $visite->type_traitement,
            $visite->structure_externe,
            $visite->cout_soins,
            $visite->materiels->map(fn ($m) => $m->nom.' x'.$m->quantite)->implode('; '),
            $visite->autre_materiel,
            $visite->cout_autre_materiel,
            $visite->observations,
        ];
    }
}
