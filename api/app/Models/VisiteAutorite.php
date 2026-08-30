<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisiteAutorite extends Model
{
    protected $table = 'visites_autorites';

    protected $fillable = [
        'school_id',
        'annee_scolaire_id',
        'date_visite',
        'qualite_autorite',
        'nature_visite',
        'objectifs',
        'observations',
    ];

    protected function casts(): array
    {
        return ['date_visite' => 'date'];
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
