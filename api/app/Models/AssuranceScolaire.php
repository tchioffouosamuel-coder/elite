<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssuranceScolaire extends Model
{
    protected $table = 'assurances_scolaires';

    protected $fillable = [
        'school_id',
        'annee_scolaire_id',
        'libelle',
        'effectif',
        'nom_assureur',
        'numero_police',
    ];

    protected function casts(): array
    {
        return ['effectif' => 'integer'];
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
