<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Budget alloué à un membre du personnel : une enveloppe qu'il gère lui-même
 * sous sa responsabilité. Ne se supprime pas une fois entamé : seule une
 * clôture tracée le neutralise, comme une avance sur salaire — le solde
 * restant se déduit toujours des dépenses imputées, jamais stocké.
 */
class BudgetPersonnel extends Model
{
    protected $table = 'budgets_personnel';

    protected $fillable = [
        'school_id', 'personnel_id', 'annee_scolaire_id', 'libelle', 'montant_alloue',
        'date_allocation', 'note_gestion', 'alloue_par', 'annule_le', 'annule_par', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'date_allocation' => 'date',
            'montant_alloue' => 'integer',
            'annule_le' => 'datetime',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Seuls les budgets non clôturés peuvent encore recevoir des dépenses. */
    public function scopeValides(Builder $query): Builder
    {
        return $query->whereNull('annule_le');
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function allouePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alloue_par');
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class, 'budget_personnel_id');
    }

    /** Somme des dépenses effectivement imputées — une dépense annulée ne pèse pas sur le budget. */
    public function getMontantDepenseAttribute(): int
    {
        return (int) $this->depenses->where('statut', '!=', 'annulee')->sum('montant');
    }

    public function getSoldeAttribute(): int
    {
        return max(0, $this->montant_alloue - $this->montant_depense);
    }

    public function getStatutAttribute(): string
    {
        return match (true) {
            $this->estAnnule() => 'annule',
            $this->solde === 0 => 'epuise',
            default => 'actif',
        };
    }
}
