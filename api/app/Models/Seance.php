<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seance extends Model
{
    protected $fillable = [
        'school_id', 'classe_id', 'classe_matiere_id', 'trimestre_id', 'emploi_du_temps_id',
        'date_seance', 'heure_debut', 'heure_fin', 'salle', 'contenu', 'statut',
        'observations', 'donnees_personnalisees',
    ];

    protected function casts(): array
    {
        return ['date_seance' => 'date', 'donnees_personnalisees' => 'array'];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }

    public function trimestre(): BelongsTo
    {
        return $this->belongsTo(Trimestre::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    /** Leçons du programme traitées pendant la séance. */
    public function lecons(): BelongsToMany
    {
        return $this->belongsToMany(ProgressionItem::class, 'lecon_seance')->withTimestamps();
    }

    /** Durée de la séance en heures, base du cumul d'absences. */
    public function dureeHeures(): float
    {
        $debut = strtotime($this->heure_debut);
        $fin = strtotime($this->heure_fin);

        return $fin > $debut ? round(($fin - $debut) / 3600, 2) : 0.0;
    }
}
