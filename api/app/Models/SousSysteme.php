<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sous-système d'enseignement : Francophone, Anglophone, Bilingue
 * Spécifie la langue d'instruction d'une classe.
 */
class SousSysteme extends Model
{
    protected $fillable = ['school_id', 'code', 'nom', 'description'];

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    public function niveaux(): HasMany
    {
        return $this->hasMany(Niveau::class);
    }
}
