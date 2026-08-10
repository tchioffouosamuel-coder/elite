<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasseMatiere extends Model
{
    protected $fillable = [
        'classe_id', 'matiere_id', 'personnel_id', 'coefficient',
        'quota_horaire', 'groupe', 'competences', 'statut',
    ];

    protected function casts(): array
    {
        return ['coefficient' => 'decimal:1'];
    }

    /** Filtre par école via la classe (classe_matieres n'a pas school_id en propre). */
    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->whereHas('classe', fn ($q) => $q->where('school_id', $schoolId));
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
