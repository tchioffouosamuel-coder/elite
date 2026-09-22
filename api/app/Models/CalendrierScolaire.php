<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendrierScolaire extends Model
{
    protected $fillable = [
        'annee_scolaire_id', 'date', 'est_ouvert', 'motif',
        'sous_systeme_id', 'niveau_id', 'classe_id',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'est_ouvert' => 'boolean'];
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function sousSysteme(): BelongsTo
    {
        return $this->belongsTo(SousSysteme::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function scopeForSchool(Builder $query, int|array $schoolIds): Builder
    {
        return $query->whereHas('anneeScolaire', fn ($q) => is_array($schoolIds)
            ? $q->whereIn('school_id', $schoolIds)
            : $q->where('school_id', $schoolIds));
    }
}