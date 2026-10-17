<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Appréciation courte (60 caractères) qu'un enseignant laisse sur le travail
 * d'un élève, pour sa matière (secondaire) ou sa compétence (primaire,
 * maternelle), à une séquence précise.
 *
 * Distincte de {@see ObservationEvaluation} (texte libre par trimestre, sans
 * limite de longueur) et de {@see Appreciation} (référentiel qualitatif de
 * maternelle, émoji/couleur) : ici, un commentaire bref que SEULES les
 * séquences 1 et 2 d'un trimestre portent, et que le bulletin du premier
 * trimestre affiche l'une sous l'autre pour chaque matière.
 */
class AppreciationSequence extends Model
{
    protected $table = 'appreciations_sequence';

    protected $fillable = [
        'eleve_id', 'classe_matiere_id', 'classe_competence_id',
        'sequence_id', 'texte', 'saisi_par',
    ];

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas('eleve', fn ($q) => is_array($schoolId) ? $q->whereIn('school_id', $schoolId) : $q->where('school_id', $schoolId));
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }

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
