<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matiere extends Model
{
    protected $fillable = [
        'school_id', 'departement_id', 'competence_id', 'nom', 'nom_en',
        'abbreviation', 'statut',
    ];

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    /**
     * Compétence dont cette matière est un contenu — primaire et maternelle
     * seulement. Au secondaire la matière est elle-même l'unité notée et n'en
     * relève d'aucune.
     */
    public function competence(): BelongsTo
    {
        return $this->belongsTo(Competence::class);
    }

    public function classeMatieres(): HasMany
    {
        return $this->hasMany(ClasseMatiere::class);
    }
}
