<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function getValeurTotaleAttribute(): int
    {
        return $this->quantite * (int) $this->valeur_unitaire;
    }
}
