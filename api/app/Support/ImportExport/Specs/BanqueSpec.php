<?php

namespace App\Support\ImportExport\Specs;

use App\Models\Banque;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

class BanqueSpec implements SpecificationModele
{
    public function modele(): string
    {
        return Banque::class;
    }

    public function colonnes(): array
    {
        return [
            'banque' => 'nom', 'nom' => 'nom',
            'code' => 'code', 'swift' => 'code',
        ];
    }

    public function libellesTemplate(): array
    {
        return ['nom' => 'Banque', 'code' => 'Code / SWIFT'];
    }

    public function regles(): array
    {
        return ['nom' => ['required', 'string']];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return ['nom' => $ligne['nom']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter(['code' => $ligne['code'] ?? null], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return Banque::query()->orderBy('nom');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $enregistrement->{$cle};
    }
}
