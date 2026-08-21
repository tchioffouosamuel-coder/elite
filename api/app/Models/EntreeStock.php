<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Réapprovisionnement du point de vente : la quantité qui entre en stock et
 * ce qu'elle a coûté. C'est cette pièce qui met à jour le coût unitaire moyen
 * de l'article — sans elle, la marge d'une vente se calculerait sur le prix
 * d'achat du premier lot, indéfiniment.
 */
class EntreeStock extends Model
{
    protected $table = 'entrees_stock';

    protected $fillable = [
        'school_id', 'annee_scolaire_id', 'inventaire_article_id', 'date_entree',
        'quantite', 'cout_unitaire', 'fournisseur', 'reference', 'enregistre_par', 'note',
    ];

    protected function casts(): array
    {
        return [
            'date_entree' => 'date',
            'quantite' => 'integer',
            'cout_unitaire' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function getCoutTotalAttribute(): int
    {
        return $this->quantite * $this->cout_unitaire;
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(InventaireArticle::class, 'inventaire_article_id');
    }

    public function enregistreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function ecritures(): MorphMany
    {
        return $this->morphMany(EcritureComptable::class, 'origine');
    }
}
