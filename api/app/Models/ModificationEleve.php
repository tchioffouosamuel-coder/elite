<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Révision de fiche (identité / santé) proposée par un parent pour un
 * enfant déjà scolarisé, en attente d'examen.
 *
 * @see \App\Services\ModificationEleveService pour la validation (qui
 *      applique `donnees` à l'élève réel) et le rejet.
 */
class ModificationEleve extends Model
{
    protected $table = 'modifications_eleves';

    protected $fillable = [
        'school_id',
        'eleve_id',
        'tuteur_id',
        'donnees',
        'statut',
        'motif_rejet',
        'traite_par',
        'traite_le',
    ];

    protected function casts(): array
    {
        return [
            'donnees' => 'array',
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

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(Tuteur::class);
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
