<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande d'avance sur salaire soumise par un employé lui-même, en attente
 * d'examen par un titulaire de `finance.paie`.
 *
 * @see \App\Services\DemandeAvanceSalaireService pour la validation (qui
 *      crée l'avance réelle via AvanceSalaireService) et le rejet.
 */
class DemandeAvanceSalaire extends Model
{
    protected $table = 'demandes_avance_salaire';

    protected $fillable = [
        'school_id',
        'personnel_id',
        'montant',
        'nombre_mois',
        'mensualite',
        'mois_debut_remboursement',
        'motif',
        'statut',
        'motif_rejet',
        'avance_salaire_id',
        'traite_par',
        'traite_le',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'nombre_mois' => 'integer',
            'mensualite' => 'integer',
            'mois_debut_remboursement' => 'date',
            'traite_le' => 'datetime',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function avanceSalaire(): BelongsTo
    {
        return $this->belongsTo(AvanceSalaire::class);
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
