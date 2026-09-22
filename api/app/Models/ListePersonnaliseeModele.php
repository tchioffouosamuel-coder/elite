<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Modèle réutilisable pour les listes personnalisées d'enseignants et de transport. */
class ListePersonnaliseeModele extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'domaine',
        'titre_fr',
        'titre_en',
        'colonnes',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'colonnes' => 'array',
            'options' => 'array',
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
