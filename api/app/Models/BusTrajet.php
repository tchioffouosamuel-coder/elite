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
    ];

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(BusVehicule::class, 'vehicule_id');
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
