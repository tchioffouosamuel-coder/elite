<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Brouillon de préinscription soumis par un parent, en attente d'examen.
 *
 * @see \App\Services\PreinscriptionService pour la validation (qui applique
 *      les données aux tables réelles) et le rejet.
 */
class Preinscription extends Model
{
    protected $fillable = [
        'school_id',
        'tuteur_id',
        'eleve_id',
        'type',
        'statut',
        'donnees_eleve',
        'donnees_tuteurs',
        'note_admin',
        'montant_verser',
        'mode_versement',
        'reference_externe',
        'rubriques_versement',
        'versement_id',
        'motif_rejet',
        'traite_par',
        'traite_le',
    ];

    protected function casts(): array
    {
        return [
            'donnees_eleve' => 'array',
            'donnees_tuteurs' => 'array',
            'rubriques_versement' => 'array',
            'traite_le' => 'datetime',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(Tuteur::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function versement(): BelongsTo
    {
        return $this->belongsTo(Versement::class);
    }
}
