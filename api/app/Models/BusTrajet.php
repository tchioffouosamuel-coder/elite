<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusTrajet extends Model
{
    protected $fillable = [
        'school_id', 'vehicule_id', 'nom', 'description',
        'tarif_aller_simple', 'tarif_retour_simple', 'tarif_aller_retour',
    ];

    protected function casts(): array
    {
        return [
            'tarif_aller_simple' => 'integer',
            'tarif_retour_simple' => 'integer',
            'tarif_aller_retour' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Tarif du trajet pour l'option choisie — figé sur la souscription à sa création. */
    public function tarifPour(string $option): ?int
    {
        return match ($option) {
            'aller_simple' => $this->tarif_aller_simple,
            'retour_simple' => $this->tarif_retour_simple,
            default => $this->tarif_aller_retour,
        };
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(BusVehicule::class, 'vehicule_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function arrets(): HasMany
    {
        return $this->hasMany(BusArret::class, 'trajet_id')->orderBy('ordre');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(BusAffectation::class, 'trajet_id');
    }
}
