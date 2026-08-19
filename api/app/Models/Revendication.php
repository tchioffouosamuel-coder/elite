<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Revendication extends Model
{
    public const TYPES = ['note', 'decision', 'autre'];

    public const STATUTS = ['en_attente', 'en_cours', 'resolue', 'rejetee'];

    public const LIBELLES_TYPES = [
        'note' => 'Contestation de note',
        'decision' => 'Contestation de décision',
        'autre' => 'Autre',
    ];

    protected $fillable = [
        'eleve_id', 'classe_matiere_id', 'trimestre_id', 'type', 'objet', 'motif',
        'statut', 'decision', 'date_reception', 'date_traitement', 'enregistre_par', 'traite_par',
    ];

    protected function casts(): array
    {
        return [
            'date_reception' => 'date',
            'date_traitement' => 'date',
        ];
    }

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

    public function trimestre(): BelongsTo
    {
        return $this->belongsTo(Trimestre::class);
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'enregistre_par');
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'traite_par');
    }
}
