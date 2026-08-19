<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventaireArticle extends Model
{
    protected $table = 'inventaire_articles';

    protected $fillable = [
        'school_id', 'nom', 'categorie', 'quantite', 'etat', 'localisation',
        'valeur_unitaire', 'date_acquisition', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'valeur_unitaire' => 'integer',
            'date_acquisition' => 'date',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function getValeurTotaleAttribute(): int
    {
        return $this->quantite * (int) $this->valeur_unitaire;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
