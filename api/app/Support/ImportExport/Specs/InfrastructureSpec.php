<?php

namespace App\Support\ImportExport\Specs;

use App\Models\Infrastructure;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

class InfrastructureSpec implements SpecificationModele
{
    public function modele(): string
    {
        return Infrastructure::class;
    }

    public function colonnes(): array
    {
        return [
            'type' => 'type',
            'libelle' => 'libelle', 'nom' => 'libelle',
            'materiau' => 'materiau',
            'etat' => 'etat',
            'quantite' => 'quantite',
            'besoin_quantite' => 'besoin_quantite', 'besoin' => 'besoin_quantite',
            'observations' => 'observations',
        ];
    }

    public function libellesTemplate(): array
    {
        return [
            'type' => 'Type', 'libelle' => 'Libellé', 'materiau' => 'Matériau', 'etat' => 'État',
            'quantite' => 'Quantité', 'besoin_quantite' => 'Besoin (quantité)', 'observations' => 'Observations',
        ];
    }

    public function regles(): array
    {
        return [
            'type' => ['required', 'string'],
            'libelle' => ['required', 'string'],
            'quantite' => ['nullable', 'integer', 'min:0'],
            'besoin_quantite' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return ['school_id' => $schoolId, 'type' => $ligne['type'], 'libelle' => $ligne['libelle']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'materiau' => $ligne['materiau'] ?? null,
            'etat' => $ligne['etat'] ?? null,
            'quantite' => isset($ligne['quantite']) ? (int) $ligne['quantite'] : null,
            'besoin_quantite' => isset($ligne['besoin_quantite']) ? (int) $ligne['besoin_quantite'] : null,
            'observations' => $ligne['observations'] ?? null,
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return Infrastructure::forSchool($schoolId)->orderBy('type')->orderBy('libelle');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $enregistrement->{$cle};
    }
}
