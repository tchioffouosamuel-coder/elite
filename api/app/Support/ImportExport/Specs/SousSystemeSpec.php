<?php

namespace App\Support\ImportExport\Specs;

use App\Models\SousSysteme;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

class SousSystemeSpec implements SpecificationModele
{
    public function modele(): string
    {
        return SousSysteme::class;
    }

    public function colonnes(): array
    {
        return ['code' => 'code', 'nom' => 'nom', 'sous_systeme' => 'nom', 'description' => 'description'];
    }

    public function libellesTemplate(): array
    {
        return ['code' => 'Code', 'nom' => 'Nom', 'description' => 'Description'];
    }

    public function regles(): array
    {
        return ['code' => ['required', 'string'], 'nom' => ['required', 'string']];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return ['school_id' => $schoolId, 'code' => $ligne['code']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'nom' => $ligne['nom'] ?? null,
            'description' => $ligne['description'] ?? null,
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return SousSysteme::forSchool($schoolId)->orderBy('nom');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $enregistrement->{$cle};
    }
}
