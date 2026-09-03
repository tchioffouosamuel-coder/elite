<?php

namespace App\Support\ImportExport\Specs;

use App\Models\Appreciation;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

class AppreciationSpec implements SpecificationModele
{
    public function modele(): string
    {
        return Appreciation::class;
    }

    public function colonnes(): array
    {
        return [
            'appreciation' => 'label_fr', 'label_fr' => 'label_fr', 'nom_fr' => 'label_fr',
            'label_en' => 'label_en', 'nom_en' => 'label_en',
            'emoji' => 'emoji', 'couleur' => 'couleur', 'ordre' => 'ordre',
        ];
    }

    public function libellesTemplate(): array
    {
        return [
            'label_fr' => 'Appréciation (FR)', 'label_en' => 'Appréciation (EN)',
            'emoji' => 'Emoji', 'couleur' => 'Couleur', 'ordre' => 'Ordre',
        ];
    }

    public function regles(): array
    {
        return ['label_fr' => ['required', 'string']];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return ['school_id' => $schoolId, 'label_fr' => $ligne['label_fr']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'label_en' => $ligne['label_en'] ?? null,
            'emoji' => $ligne['emoji'] ?? null,
            'couleur' => $ligne['couleur'] ?? null,
            'ordre' => isset($ligne['ordre']) ? (int) $ligne['ordre'] : null,
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return Appreciation::forSchool($schoolId)->orderBy('ordre');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $enregistrement->{$cle};
    }
}
