<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenteDenree extends Model
{
    protected $table = 'ventes_denrees';

    protected $fillable = [
        'school_id',
        'annee_scolaire_id',
        'nature',
        'vendeur_nom',
        'dossier_medical_ok',
        'frais_verses',
        'gestion_frais',
    ];

    protected function casts(): array
    {
        return [
            'dossier_medical_ok' => 'boolean',
            'frais_verses' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
