<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Modèle réutilisable (titre bilingue + colonnes) pour la liste personnalisée de classe — cf. ListeClassePersonnaliseeService. */
class ListeClasseModele extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'titre_fr',
        'titre_en',
        'colonnes',
        'moyenne_type',
    ];

    protected function casts(): array
    {
        return [
            'colonnes' => 'array',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
