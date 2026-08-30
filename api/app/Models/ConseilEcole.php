<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConseilEcole extends Model
{
    protected $table = 'conseils_ecole';

    protected $fillable = [
        'school_id',
        'annee_scolaire_id',
        'existe',
        'date_ag_elective',
        'duree_mandat',
        'fin_mandat',
        'president_nom',
        'president_fonction',
        'president_telephone',
        'statut_projet_ecole',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'existe' => 'boolean',
            'date_ag_elective' => 'date',
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
