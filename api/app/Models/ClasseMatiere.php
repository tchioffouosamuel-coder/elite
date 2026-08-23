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
        // Cartouche du gabarit de progression secondaire — sans objet pour le
        // primaire/maternelle, dont le cartouche n'a ni Specialty ni Module.
        'module_competence', 'specialite',
    ];

    protected function casts(): array
    {
        return ['coefficient' => 'decimal:1'];
    }

    /** Filtre par école via la classe (classe_matieres n'a pas school_id en propre). */
    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas('classe', fn ($q) => is_array($schoolId) ? $q->whereIn('school_id', $schoolId) : $q->where('school_id', $schoolId));
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

    public function progressionColonnes(): HasMany
    {
        return $this->hasMany(ProgressionColonne::class)->orderBy('ordre');
    }
}
