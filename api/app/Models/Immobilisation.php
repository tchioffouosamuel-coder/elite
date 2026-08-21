<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bien immobilisé : ce que la dépense a construit plutôt que consommé.
 *
 * Le classeur de l'établissement passait la construction en charge l'année
 * même — 202 millions sur onze exercices, sans qu'aucun amortissement ne
 * vienne jamais l'étaler. L'immobilisation porte le montant et la durée sur
 * laquelle il revient au résultat.
 */
class Immobilisation extends Model
{
    protected $fillable = [
        'school_id', 'depense_id', 'compte_comptable_id', 'libelle',
        'montant', 'date_mise_en_service', 'duree_annees', 'cede_le',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'duree_annees' => 'integer',
            'date_mise_en_service' => 'date',
            'cede_le' => 'datetime',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Un bien cédé ne se dote plus : il est sorti du patrimoine. */
    public function scopeEnService(Builder $query): Builder
    {
        return $query->whereNull('cede_le');
    }

    public function depense(): BelongsTo
    {
        return $this->belongsTo(Depense::class);
    }

    public function amortissements(): HasMany
    {
        return $this->hasMany(Amortissement::class);
    }

    /** Dotation d'un exercice plein, arrondie au franc supérieur. */
    public function dotationAnnuelle(): int
    {
        return $this->duree_annees > 0 ? (int) ceil($this->montant / $this->duree_annees) : 0;
    }

    public function getCumulAmortiAttribute(): int
    {
        return (int) $this->amortissements->sum('montant');
    }

    /** Ce qu'il reste à étaler : la dernière dotation solde le reliquat. */
    public function getValeurResiduelleAttribute(): int
    {
        return max(0, $this->montant - $this->cumul_amorti);
    }
}
