<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusVehicule extends Model
{
    protected $fillable = [
        'school_id', 'immatriculation', 'marque', 'capacite', 'chauffeur_id', 'statut',
    ];

    protected function casts(): array
    {
        return ['capacite' => 'integer'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'chauffeur_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function trajets(): HasMany
    {
        return $this->hasMany(BusTrajet::class, 'vehicule_id');
    }
}
