<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Attribution d'une compétence à une classe : c'est ici que vivent
 * l'enseignant et le bloc d'affichage du bulletin, comme
 * {@see ClasseMatiere} le fait au secondaire pour la matière.
 *
 * Attribuer une compétence crée d'office l'affectation de chacune de ses
 * matières à la classe (cf. `CompetenceAttributionService`) : l'utilisateur
 * choisit un bloc, pas une liste de matières une à une.
 */
class ClasseCompetence extends Model
{
    protected $fillable = ['classe_id', 'competence_id', 'personnel_id', 'groupe', 'statut'];

    protected function casts(): array
    {
        return ['groupe' => 'integer'];
    }

    /** Filtre par école via la classe : la table n'a pas de school_id en propre. */
    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas(
            'classe',
            fn ($q) => is_array($schoolId) ? $q->whereIn('school_id', $schoolId) : $q->where('school_id', $schoolId),
        );
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function competence(): BelongsTo
    {
        return $this->belongsTo(Competence::class);
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
