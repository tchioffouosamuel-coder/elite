<?php

namespace App\Support\ImportExport\Specs;

use App\Models\InventaireArticle;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

class InventaireArticleSpec implements SpecificationModele
{
    public function modele(): string
    {
        return InventaireArticle::class;
    }

    public function colonnes(): array
    {
        return [
            'nom' => 'nom', 'article' => 'nom',
            'code_barre' => 'code_barre', 'code' => 'code_barre',
            'categorie' => 'categorie',
            'quantite' => 'quantite',
            'etat' => 'etat',
            'localisation' => 'localisation',
            'valeur_unitaire' => 'valeur_unitaire', 'cout_unitaire' => 'valeur_unitaire',
            'prix_vente' => 'prix_vente',
            'notes' => 'notes',
        ];
    }

    public function libellesTemplate(): array
    {
        return [
            'nom' => 'Article', 'code_barre' => 'Code-barre', 'categorie' => 'Catégorie',
            'quantite' => 'Quantité', 'etat' => 'État', 'localisation' => 'Localisation',
            'valeur_unitaire' => 'Valeur unitaire', 'prix_vente' => 'Prix de vente', 'notes' => 'Notes',
        ];
    }

    public function regles(): array
    {
        return [
            'nom' => ['required', 'string'],
            'quantite' => ['nullable', 'integer', 'min:0'],
            'valeur_unitaire' => ['nullable', 'numeric', 'min:0'],
            'prix_vente' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return isset($ligne['code_barre'])
            ? ['school_id' => $schoolId, 'code_barre' => $ligne['code_barre']]
            : ['school_id' => $schoolId, 'nom' => $ligne['nom']];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'nom' => $ligne['nom'] ?? null,
            'categorie' => $ligne['categorie'] ?? null,
            'quantite' => isset($ligne['quantite']) ? (int) $ligne['quantite'] : null,
            'etat' => $ligne['etat'] ?? null,
            'localisation' => $ligne['localisation'] ?? null,
            'valeur_unitaire' => $ligne['valeur_unitaire'] ?? null,
            'prix_vente' => $ligne['prix_vente'] ?? null,
            'notes' => $ligne['notes'] ?? null,
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return InventaireArticle::forSchool($schoolId)->orderBy('nom');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $enregistrement->{$cle};
    }
}
