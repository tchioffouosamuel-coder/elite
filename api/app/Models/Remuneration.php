<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Remuneration contractuelle, historisee par date d'effet : une augmentation
 * ne reecrit pas les bulletins deja emis.
 */
class Remuneration extends Model
{
    protected $fillable = [
        'school_id', 'personnel_id', 'date_effet', 'mode', 'taux_horaire',
        'salaire_base', 'prime_anciennete',
        'prime_communication', 'prime_transport', 'prime_recherche', 'prime_performance', 'categorie',
    ];

    protected function casts(): array
    {
        return [
            'date_effet' => 'date:Y-m-d',
            'taux_horaire' => 'integer',
            'salaire_base' => 'integer',
            'prime_anciennete' => 'integer',
            'prime_communication' => 'integer',
            'prime_transport' => 'integer',
            'prime_recherche' => 'integer',
            'prime_performance' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    /** Vacataire payé à l'heure enseignée, et non au mois. */
    public function estHoraire(): bool
    {
        return $this->mode === 'horaire';
    }

    /** Somme des gains : c'est le brut avant retenues. */
    public function getBrutAttribute(): int
    {
        return $this->salaire_base + $this->prime_anciennete + $this->prime_communication
            + $this->prime_transport + $this->prime_recherche + $this->prime_performance;
    }
}
