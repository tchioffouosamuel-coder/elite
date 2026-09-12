<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipementMobilier extends Model
{
    protected $table = 'equipements_mobiliers';

    protected $fillable = [
        'school_id',
        'nature',
        'date_acquisition',
        'quantite',
        'besoin_quantite',
        'prix_unitaire',
        'statut',
    ];

    protected $appends = ['prix_total', 'quantite_totale'];

    protected function casts(): array
    {
        return [
            'date_acquisition' => 'date:Y-m-d',
            'quantite' => 'integer',
            'besoin_quantite' => 'integer',
            'prix_unitaire' => 'decimal:2',
        ];
    }

    /** Quantité en stock + besoin restant : ce que compterait l'école une fois le besoin comblé. */
    protected function quantiteTotale(): Attribute
    {
        return Attribute::get(fn () => $this->quantite + ($this->besoin_quantite ?? 0));
    }

    protected function prixTotal(): Attribute
    {
        return Attribute::get(fn () => $this->prix_unitaire === null ? null : round($this->quantite * (float) $this->prix_unitaire, 2));
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
