<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Niveau d'enseignement interne à un établissement (SIL, CP, CE1… au primaire,
 * Petite/Moyenne/Grande section en maternelle), piloté par un animateur de
 * niveau — l'équivalent primaire du chef de département du secondaire.
 */
class NiveauScolaire extends Model
{
    protected $fillable = ['school_id', 'code', 'libelle', 'ordre', 'animateur_personnel_id'];

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function animateur(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'animateur_personnel_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }
}
