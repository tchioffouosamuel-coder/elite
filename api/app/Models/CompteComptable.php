<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan de comptes du complexe. Les classes 6 et 7 reprennent code pour code la
 * nomenclature de l'« État de synthèse des charges et dépenses » tenu par
 * l'établissement ; les classes 1 à 5 sont des comptes techniques que la
 * partie double exige et que le document ne connaît pas.
 *
 * `nature` est la propriété qui décide du résultat : seule l'exploitation
 * entre dans la balance de l'exercice. L'investissement immobilier construit
 * un actif, les apports de l'exploitant relèvent du haut de bilan — les
 * confondre avec des charges est précisément ce qui faisait afficher un
 * déficit là où l'exploitation dégageait un excédent.
 */
class CompteComptable extends Model
{
    // Laravel devinerait « compte_comptables » : le pluriel porte sur le premier mot.
    protected $table = 'comptes_comptables';

    protected $fillable = [
        'code', 'libelle', 'libelle_en', 'classe', 'sens',
        'nature', 'assiette', 'montant_unitaire', 'ordre', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'classe' => 'integer',
            'montant_unitaire' => 'integer',
            'ordre' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Charges et produits qui composent le résultat de l'exercice. */
    public function scopeExploitation(Builder $query): Builder
    {
        return $query->where('nature', 'exploitation');
    }

    /** Construit un actif : ne pèse sur le résultat que par son amortissement. */
    public function scopeInvestissement(Builder $query): Builder
    {
        return $query->where('nature', 'investissement');
    }

    /** Apports et dépôts de l'exploitant : ni charge, ni produit. */
    public function scopeCapital(Builder $query): Builder
    {
        return $query->where('nature', 'capital');
    }

    /** Les comptes que le document présente : charges, produits, capital de l'exploitant. */
    public function scopeDuDocument(Builder $query): Builder
    {
        return $query->whereIn('classe', [6, 7])->orWhere('code', '100');
    }

    /** Prélèvements dont le montant se calcule sur l'effectif, non arbitré. */
    public function scopeParEleve(Builder $query): Builder
    {
        return $query->where('assiette', 'par_eleve');
    }

    /** Un compte de charge se lit en positif quel que soit son sens d'écriture. */
    public function estCharge(): bool
    {
        return $this->classe === 6;
    }

    public function estProduit(): bool
    {
        return $this->classe === 7;
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }

    public function ecritures(): HasMany
    {
        return $this->hasMany(EcritureComptable::class);
    }
}
