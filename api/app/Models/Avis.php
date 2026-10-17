<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis libre déposé par un parent, un enseignant ou un membre du personnel
 * (espace d'évaluation, point 24 des retours). L'auteur est toujours un
 * `User` — son rôle au moment de la lecture (`user->getRoleNames()`) dit de
 * quel côté vient l'avis, comme pour {@see Observation}.
 */
class Avis extends Model
{
    protected $table = 'avis';

    protected $fillable = ['school_id', 'user_id', 'sujet', 'contenu', 'note'];

    protected function casts(): array
    {
        return [
            'note' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
