<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Colonne libre de la fiche de progression — jusqu'à dix par matière/classe. */
class ProgressionColonne extends Model
{
    public const MAX_PAR_MATIERE = 10;

    protected $fillable = ['classe_matiere_id', 'libelle', 'ordre'];

    /** Scopé par école via la classe qui porte l'affectation, comme ProgressionItem. */
    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas('classeMatiere.classe', fn ($q) => is_array($schoolId) ? $q->whereIn('school_id', $schoolId) : $q->where('school_id', $schoolId));
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }
}
