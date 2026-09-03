<?php

namespace App\Support\ImportExport\Specs;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\GrilleFrais;
use App\Support\ImportExport\Resolveur;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

/** Grille des frais de scolarité par classe, pour l'année scolaire active de l'école. */
class GrilleFraisSpec implements SpecificationModele
{
    public function modele(): string
    {
        return GrilleFrais::class;
    }

    public function colonnes(): array
    {
        return ['classe' => 'classe', 'montant' => 'montant', 'frais_scolarite' => 'montant'];
    }

    public function libellesTemplate(): array
    {
        return ['classe' => 'Classe', 'montant' => 'Montant (FCFA)'];
    }

    public function regles(): array
    {
        return ['classe' => ['required', 'string'], 'montant' => ['required', 'numeric', 'min:0']];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return [
            'school_id' => $schoolId,
            'annee_scolaire_id' => $this->anneeActiveId($schoolId),
            'classe_id' => Resolveur::id(Classe::class, $schoolId, $ligne['classe'] ?? null, ['nom', 'sigle']),
        ];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return ['montant' => (int) round((float) $ligne['montant'])];
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return GrilleFrais::with('classe:id,nom')->when(
            is_array($schoolId),
            fn (Builder $q) => $q->whereIn('school_id', $schoolId),
            fn (Builder $q) => $q->where('school_id', $schoolId),
        );
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $cle === 'classe' ? $enregistrement->classe?->nom : $enregistrement->{$cle};
    }

    private function anneeActiveId(int $schoolId): ?int
    {
        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->value('id');
    }
}
