<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compétence évaluée du primaire et de la maternelle : l'unité que le bulletin
 * note et que le livret officiel nomme.
 *
 * Elle dit CE QUE l'on évalue — « Langue et communication », dont la lecture,
 * l'écriture et la langue nationale sont les matières. COMMENT on la note
 * (barème, volets, répartition des points) appartient à son attribution à une
 * classe ({@see ClasseCompetence}) : la même compétence ne pèse pas le même
 * poids au CP et au CM2, et le volet pratique n'est évalué que là où la classe
 * le travaille.
 */
class Competence extends Model
{
    protected $fillable = [
        'school_id', 'label_fr', 'label_en', 'abbreviation', 'ordre', 'statut',
    ];

    protected function casts(): array
    {
        return ['ordre' => 'integer'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function scopeActives(Builder $query): Builder
    {
        return $query->where('statut', 'actif');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Contenu enseigné au titre de cette compétence. */
    public function matieres(): HasMany
    {
        return $this->hasMany(Matiere::class);
    }

    public function classeCompetences(): HasMany
    {
        return $this->hasMany(ClasseCompetence::class);
    }
}
