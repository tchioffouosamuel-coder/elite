<?php

namespace App\Support\ImportExport\Specs;

use App\Models\BusVehicule;
use App\Models\Personnel;
use App\Support\ImportExport\Resolveur;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

class BusVehiculeSpec implements SpecificationModele
{
    public function modele(): string
    {
        return BusVehicule::class;
    }

    public function colonnes(): array
    {
        return [
            'immatriculation' => 'immatriculation', 'plaque' => 'immatriculation',
            'marque' => 'marque',
            'couleur' => 'couleur',
            'capacite' => 'capacite',
            'chauffeur' => 'chauffeur',
            'statut' => 'statut',
        ];
    }

    public function libellesTemplate(): array
    {
        return [
            'immatriculation' => 'Immatriculation', 'marque' => 'Marque', 'couleur' => 'Couleur',
            'capacite' => 'Capacité', 'chauffeur' => 'Chauffeur', 'statut' => 'Statut',
        ];
    }

    public function regles(): array
    {
        return ['immatriculation' => ['required', 'string'], 'capacite' => ['nullable', 'integer', 'min:1']];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return ['school_id' => $schoolId, 'immatriculation' => $ligne['immatriculation']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'marque' => $ligne['marque'] ?? null,
            'couleur' => $ligne['couleur'] ?? null,
            'capacite' => isset($ligne['capacite']) ? (int) $ligne['capacite'] : null,
            'chauffeur_id' => Resolveur::id(Personnel::class, $schoolId, $ligne['chauffeur'] ?? null, ['nom_complet']),
            'statut' => $ligne['statut'] ?? null,
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return BusVehicule::forSchool($schoolId)->with('chauffeur:id,nom_complet')->orderBy('immatriculation');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $cle === 'chauffeur' ? $enregistrement->chauffeur?->nom_complet : $enregistrement->{$cle};
    }
}
