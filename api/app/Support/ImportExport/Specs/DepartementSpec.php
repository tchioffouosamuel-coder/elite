<?php

namespace App\Support\ImportExport\Specs;

use App\Models\Departement;
use App\Models\Personnel;
use App\Support\ImportExport\Resolveur;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

class DepartementSpec implements SpecificationModele
{
    public function modele(): string
    {
        return Departement::class;
    }

    public function colonnes(): array
    {
        return [
            'nom' => 'nom', 'departement' => 'nom',
            'chef_de_departement' => 'chef', 'chef_departement' => 'chef', 'chef' => 'chef',
        ];
    }

    public function libellesTemplate(): array
    {
        return ['nom' => 'Département', 'chef' => 'Chef de département'];
    }

    public function regles(): array
    {
        return ['nom' => ['required', 'string']];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return ['school_id' => $schoolId, 'nom' => $ligne['nom']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'head_personnel_id' => Resolveur::id(Personnel::class, $schoolId, $ligne['chef'] ?? null, ['nom_complet']),
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return Departement::forSchool($schoolId)->with('headPersonnel:id,nom_complet')->orderBy('nom');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $cle === 'chef' ? $enregistrement->headPersonnel?->nom_complet : $enregistrement->{$cle};
    }
}
