<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tuteur extends Model
{
    protected $fillable = ['school_id', 'user_id', 'nom_complet', 'telephone', 'email', 'profession', 'lieu_service', 'adresse'];

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'eleve_tuteur')
            ->withPivot(['lien_parente', 'is_principal'])
            ->withTimestamps();
    }
}
