<?php

namespace App\Support\ImportExport\Specs;

use App\Models\Niveau;
use App\Models\SousSysteme;
use App\Support\ImportExport\Resolveur;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

/** Niveaux MINESEC (SIL, CP, 6e…) — référentiel partiellement global : `school_id` peut être vide (cf. NiveauController). */
class NiveauSpec implements SpecificationModele
{
    public function modele(): string
    {
        return Niveau::class;
    }

    public function colonnes(): array
    {
        return [
            'code' => 'code',
            'nom_fr' => 'name_fr', 'nom' => 'name_fr', 'name_fr' => 'name_fr',
            'nom_en' => 'name_en', 'name_en' => 'name_en',
            'sous_systeme' => 'sous_systeme', 'sous_system' => 'sous_systeme',
        ];
    }

    public function libellesTemplate(): array
    {
        return ['code' => 'Code', 'name_fr' => 'Nom (FR)', 'name_en' => 'Nom (EN)', 'sous_systeme' => 'Sous-système'];
    }

    public function regles(): array
    {
        // `name_en` est NOT NULL en base (système bilingue) : un niveau sans
        // traduction anglaise ne peut pas être créé, seulement mis à jour.
        return ['code' => ['required', 'string'], 'name_fr' => ['required', 'string'], 'name_en' => ['required', 'string']];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return ['school_id' => $schoolId, 'code' => $ligne['code']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'name_fr' => $ligne['name_fr'] ?? null,
            'name_en' => $ligne['name_en'] ?? null,
            'sous_system_id' => Resolveur::id(SousSysteme::class, $schoolId, $ligne['sous_systeme'] ?? null, ['nom', 'code']),
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        $query = Niveau::with('sousSysteme:id,nom');

        return (is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId))
            ->orderBy('code');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $cle === 'sous_systeme' ? $enregistrement->sousSysteme?->nom : $enregistrement->{$cle};
    }
}
