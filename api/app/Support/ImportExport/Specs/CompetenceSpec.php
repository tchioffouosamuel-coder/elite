<?php

namespace App\Support\ImportExport\Specs;

use App\Models\Competence;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

class CompetenceSpec implements SpecificationModele
{
    public function modele(): string
    {
        return Competence::class;
    }

    public function colonnes(): array
    {
        return [
            'competence' => 'label_fr', 'label_fr' => 'label_fr', 'nom_fr' => 'label_fr',
            'label_en' => 'label_en', 'nom_en' => 'label_en',
            'abbreviation' => 'abbreviation', 'sigle' => 'abbreviation',
            'notation' => 'notation',
            'ordre' => 'ordre',
        ];
    }

    public function libellesTemplate(): array
    {
        return [
            'label_fr' => 'Compétence (FR)', 'label_en' => 'Compétence (EN)',
            'abbreviation' => 'Abréviation', 'notation' => 'Notation (/20 ou /10)', 'ordre' => 'Ordre',
        ];
    }

    public function regles(): array
    {
        return ['label_fr' => ['required', 'string'], 'notation' => ['nullable', 'integer']];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return ['school_id' => $schoolId, 'label_fr' => $ligne['label_fr']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'label_en' => $ligne['label_en'] ?? null,
            'abbreviation' => $ligne['abbreviation'] ?? null,
            'notation' => isset($ligne['notation']) ? (int) $ligne['notation'] : null,
            'ordre' => isset($ligne['ordre']) ? (int) $ligne['ordre'] : null,
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return Competence::forSchool($schoolId)->orderBy('ordre')->orderBy('label_fr');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $enregistrement->{$cle};
    }
}
