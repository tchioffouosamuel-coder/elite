<?php

namespace App\Support\ImportExport\Specs;

use App\Models\AnneeScolaire;
use App\Models\TrancheScolarite;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

/** Tranches de paiement de la scolarité (1ère tranche, 2e tranche…) de l'année scolaire active. */
class TrancheScolariteSpec implements SpecificationModele
{
    public function modele(): string
    {
        return TrancheScolarite::class;
    }

    public function colonnes(): array
    {
        return [
            'libelle' => 'libelle', 'tranche' => 'libelle', 'nom' => 'libelle',
            'pourcentage' => 'pourcentage',
            'date_echeance' => 'date_echeance', 'echeance' => 'date_echeance',
            'ordre' => 'ordre',
        ];
    }

    public function libellesTemplate(): array
    {
        return ['libelle' => 'Tranche', 'pourcentage' => 'Pourcentage', 'date_echeance' => 'Date d\'échéance', 'ordre' => 'Ordre'];
    }

    public function regles(): array
    {
        return [
            'libelle' => ['required', 'string'],
            'pourcentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'date_echeance' => ['nullable', 'date'],
        ];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return [
            'school_id' => $schoolId,
            'annee_scolaire_id' => AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->value('id'),
            'libelle' => $ligne['libelle'],
        ];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'pourcentage' => $ligne['pourcentage'] ?? null,
            'date_echeance' => $ligne['date_echeance'] ?? null,
            'ordre' => isset($ligne['ordre']) ? (int) $ligne['ordre'] : null,
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return TrancheScolarite::when(
            is_array($schoolId),
            fn (Builder $q) => $q->whereIn('school_id', $schoolId),
            fn (Builder $q) => $q->where('school_id', $schoolId),
        )->orderBy('ordre');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $enregistrement->{$cle};
    }
}
