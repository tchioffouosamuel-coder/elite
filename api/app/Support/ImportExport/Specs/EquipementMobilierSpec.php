<?php

namespace App\Support\ImportExport\Specs;

use App\Models\EquipementMobilier;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

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
            'date_acquisition' => 'date_acquisition', 'date' => 'date_acquisition',
            'quantite' => 'quantite',
            'besoin_quantite' => 'besoin_quantite', 'besoin' => 'besoin_quantite',
            'prix_unitaire' => 'prix_unitaire',
            'statut' => 'statut',
        ];
    }

    public function libellesTemplate(): array
    {
        return [
            'nature' => 'Nature',
            'date_acquisition' => 'Date',
            'quantite' => 'Quantité',
            'besoin_quantite' => 'Besoin (quantité)',
            'prix_unitaire' => 'Prix unitaire',
            'statut' => 'Statut (bon, assez_bon, mauvais)',
            'prix_total' => 'Prix total (calculé)',
            'quantite_totale' => 'Quantité totale (calculée)',
        ];
    }

    public function regles(): array
    {
        return [
            'nature' => ['required', 'string'],
            'date_acquisition' => ['nullable', 'date'],
            'quantite' => ['nullable', 'integer', 'min:0'],
            'besoin_quantite' => ['nullable', 'integer', 'min:0'],
            'prix_unitaire' => ['nullable', 'numeric', 'min:0'],
            'statut' => ['nullable', 'string', Rule::in(['bon', 'assez_bon', 'mauvais'])],
        ];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return ['school_id' => $schoolId, 'nature' => $ligne['nature']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'date_acquisition' => $ligne['date_acquisition'] ?? null,
            'quantite' => isset($ligne['quantite']) ? (int) $ligne['quantite'] : null,
            'besoin_quantite' => isset($ligne['besoin_quantite']) ? (int) $ligne['besoin_quantite'] : null,
            'prix_unitaire' => isset($ligne['prix_unitaire']) ? (float) $ligne['prix_unitaire'] : null,
            'statut' => $ligne['statut'] ?? null,
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
