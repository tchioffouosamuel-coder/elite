<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventaireArticle extends Model
{
    protected $table = 'inventaire_articles';

    protected $fillable = [
        'school_id', 'nom', 'code_barre', 'categorie', 'quantite', 'etat', 'localisation',
        'valeur_unitaire', 'prix_vente', 'date_acquisition', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'valeur_unitaire' => 'integer',
            'prix_vente' => 'integer',
            'date_acquisition' => 'date',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Un article n'entre au comptoir qu'une fois son prix de vente fixé. */
    public function scopeEnVente(Builder $query): Builder
    {
        return $query->whereNotNull('prix_vente');
    }

    public function getValeurTotaleAttribute(): int
    {
        return $this->quantite * (int) $this->valeur_unitaire;
    }

    /** Ce que rapporterait l'écoulement du stock restant, au prix affiché. */
    public function getValeurVenteAttribute(): int
    {
        return $this->quantite * (int) $this->prix_vente;
    }

    public function estEnVente(): bool
    {
        return $this->prix_vente !== null;
    }

    public function lignesVente(): HasMany
    {
        return $this->hasMany(VenteFournitureLigne::class);
    }

    public function entrees(): HasMany
    {
        return $this->hasMany(EntreeStock::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
