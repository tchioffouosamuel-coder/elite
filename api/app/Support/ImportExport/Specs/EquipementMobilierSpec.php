<?php

namespace App\Support\ImportExport\Specs;

use App\Models\EquipementMobilier;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

class EquipementMobilierSpec implements SpecificationModele
{
    public function modele(): string
    {
        return EquipementMobilier::class;
    }

    public function colonnes(): array
    {
        return [
            'nature' => 'nature', 'equipement' => 'nature',
            'quantite' => 'quantite',
            'besoin_quantite' => 'besoin_quantite', 'besoin' => 'besoin_quantite',
        ];
    }

    public function libellesTemplate(): array
    {
        return ['nature' => 'Nature', 'quantite' => 'Quantité', 'besoin_quantite' => 'Besoin (quantité)'];
    }

    public function regles(): array
    {
        return [
            'nature' => ['required', 'string'],
            'quantite' => ['nullable', 'integer', 'min:0'],
            'besoin_quantite' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return ['school_id' => $schoolId, 'nature' => $ligne['nature']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'quantite' => isset($ligne['quantite']) ? (int) $ligne['quantite'] : null,
            'besoin_quantite' => isset($ligne['besoin_quantite']) ? (int) $ligne['besoin_quantite'] : null,
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return EquipementMobilier::forSchool($schoolId)->orderBy('nature');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $enregistrement->{$cle};
    }
}
