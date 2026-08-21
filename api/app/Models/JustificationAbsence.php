<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Justification d'absence déposée par un parent, avant même l'appel.
 *
 * @see \App\Services\JustificationAbsenceService::appliquerSur() pour le
 *      rapprochement automatique avec le pointage réel de l'enseignant.
 */
class JustificationAbsence extends Model
{
    protected $table = 'justifications_absences';

    public const MOTIFS = ['maladie', 'scolarite', 'permission'];

    protected $fillable = [
        'school_id',
        'eleve_id',
        'tuteur_id',
        'date_debut',
        'date_fin',
        'motif',
        'description',
        'statut',
        'presence_id',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Couvre cette date et n'a pas encore été rapprochée d'un pointage. */
    public function scopeEnAttentePour(Builder $query, int $eleveId, string $date): Builder
    {
        return $query->where('eleve_id', $eleveId)
            ->where('statut', 'en_attente')
            ->whereDate('date_debut', '<=', $date)
            ->whereDate('date_fin', '>=', $date);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(Tuteur::class);
    }

    public function presence(): BelongsTo
    {
        return $this->belongsTo(Presence::class);
    }
}
