<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fil d'observations sur un élève, partagé entre l'établissement et la
 * famille. L'auteur est toujours un `User` — son rôle au moment de la
 * lecture (`user->hasRole('parent')`) dit de quel côté vient la remarque.
 */
class Observation extends Model
{
    protected $fillable = ['school_id', 'eleve_id', 'user_id', 'contenu'];

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
