<?php

namespace App\Support\ImportExport\Specs;

use App\Models\FonctionReferentiel;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

class FonctionReferentielSpec implements SpecificationModele
{
    public function modele(): string
    {
        return FonctionReferentiel::class;
    }

    public function colonnes(): array
    {
        return [
            'fonction' => 'label_fr', 'label_fr' => 'label_fr', 'nom_fr' => 'label_fr',
            'label_en' => 'label_en', 'nom_en' => 'label_en',
        ];
    }

    public function libellesTemplate(): array
    {
        return ['label_fr' => 'Fonction (FR)', 'label_en' => 'Fonction (EN)'];
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
        return array_filter(['label_en' => $ligne['label_en'] ?? null], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return FonctionReferentiel::forSchool($schoolId)->orderBy('label_fr');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $enregistrement->{$cle};
    }
}
