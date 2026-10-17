<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Colonne libre de la fiche de progression — jusqu'à dix par matière/niveau.
 *
 * `classe_matiere_id` identifie une ligne historique, propre à une seule
 * affectation classe↔matière ; `matiere_id` + `niveau_scolaire_id`/
 * `niveau_classe` + `personnel_id` identifient une ligne partagée, commune à
 * toutes les classes où cet enseignant donne cette matière à ce niveau — cf.
 * `App\Support\Progression\ResolveurFicheProgression`.
 */
class ProgressionColonne extends Model
{
    public const MAX_PAR_MATIERE = 10;

    protected $fillable = [
        'classe_matiere_id', 'libelle', 'ordre',
        'matiere_id', 'niveau_scolaire_id', 'niveau_classe', 'personnel_id',
    ];

    /** Scopé par école via la classe qui porte l'affectation, comme ProgressionItem. */
    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->where(function (Builder $q) use ($schoolId) {
            $q->whereHas('classeMatiere.classe', fn ($q2) => is_array($schoolId) ? $q2->whereIn('school_id', $schoolId) : $q2->where('school_id', $schoolId))
                ->orWhereHas('matiere', fn ($q2) => is_array($schoolId) ? $q2->whereIn('school_id', $schoolId) : $q2->where('school_id', $schoolId));
        });
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function niveauScolaire(): BelongsTo
    {
        return $this->belongsTo(NiveauScolaire::class);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
