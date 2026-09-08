<?php

namespace App\Exports;

use App\Imports\ClasseImport;
use App\Models\Classe;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export des classes, dans la forme exacte que relit `ClasseImport` (mêmes
 * en-têtes : nom, sigle, capacité) — le fichier produit ici se corrige dans
 * un tableur et se réimporte tel quel, plutôt que de repartir d'un modèle
 * vide pour un établissement qui a déjà tout saisi.
 */
class ClasseExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param int|array<int> $schoolId */
    public function __construct(private readonly int|array $schoolId) {}

    public function collection(): Collection
    {
        return Classe::forSchool($this->schoolId)->orderBy('nom')->get();
    }

    public function headings(): array
    {
        return ClasseImport::enTetes();
    }

    public function map($classe): array
    {
        /** @var Classe $classe */
        return [
            $classe->nom,
            $classe->sigle,
            $classe->capacite,
        ];
    }
}
