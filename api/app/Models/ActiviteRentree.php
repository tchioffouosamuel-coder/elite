<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiviteRentree extends Model
{
    protected $table = 'activites_rentree';

    protected $fillable = [
        'school_id',
        'annee_scolaire_id',
        'categorie',
        'activite',
        'periode',
        'objectifs_vises',
        'prevues',
        'faites',
        'taux_realisation',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'prevues' => 'integer',
            'faites' => 'integer',
            'taux_realisation' => 'integer',
        ];
    }

    /** Taux calculé depuis prévu/fait quand il n'a pas été saisi directement (FENASSCO). */
    public function getTauxAffichageAttribute(): ?int
    {
        if ($this->taux_realisation !== null) {
            return $this->taux_realisation;
        }

        if ($this->prevues) {
            return (int) round((($this->faites ?? 0) / $this->prevues) * 100);
        }

        return null;
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
