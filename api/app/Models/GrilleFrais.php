<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tarif de reference par classe. `classe_id` nul = tarif par defaut de l'ecole. */
class GrilleFrais extends Model
{
    protected $table = 'grilles_frais';

    protected $fillable = ['school_id', 'annee_scolaire_id', 'classe_id', 'montant'];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
