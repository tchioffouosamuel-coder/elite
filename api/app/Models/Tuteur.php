<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tuteur extends Model
{
    protected $fillable = ['school_id', 'user_id', 'nom', 'prenom', 'telephone', 'email', 'profession', 'adresse'];

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'eleve_tuteur')
            ->withPivot(['lien_parente', 'is_principal'])
            ->withTimestamps();
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
