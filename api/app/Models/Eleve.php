<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Eleve extends Model
{
    protected $fillable = [
        'school_id', 'classe_id', 'matricule', 'nom', 'prenom', 'sexe',
        'date_naissance', 'lieu_naissance', 'nationalite', 'adresse',
        'photo_path', 'redoublant', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'redoublant' => 'boolean',
        ];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function tuteurs(): BelongsToMany
    {
        return $this->belongsToMany(Tuteur::class, 'eleve_tuteur')
            ->withPivot(['lien_parente', 'is_principal'])
            ->withTimestamps();
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
