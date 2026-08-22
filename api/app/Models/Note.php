<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    /** Volets d'évaluation du primaire ; le secondaire n'utilise que 'unique'. */
    public const COMPOSANTES_PRIMAIRE = ['oral', 'ecrit', 'savoir_etre', 'pratique'];

    protected $fillable = [
        'eleve_id', 'classe_matiere_id', 'classe_competence_id',
        'sequence_id', 'composante', 'valeur', 'saisi_par',
    ];

    protected function casts(): array
    {
        return ['valeur' => 'decimal:2'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas('eleve', fn ($q) => is_array($schoolId) ? $q->whereIn('school_id', $schoolId) : $q->where('school_id', $schoolId));
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    /** Renseignée au secondaire, où la matière est l'unité notée. */
    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }

    /** Renseignée au primaire et en maternelle, où l'unité notée est la compétence. */
    public function classeCompetence(): BelongsTo
    {
        return $this->belongsTo(ClasseCompetence::class);
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'saisi_par');
    }
}
