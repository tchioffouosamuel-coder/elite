<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Avance sur salaire accordée à un employé. Ne se supprime pas une fois
 * remboursée en partie : seule une annulation tracée la neutralise, comme un
 * versement de scolarité — le montant restant se déduit toujours des
 * remboursements, jamais stocké.
 */
class AvanceSalaire extends Model
{
    protected $table = 'avances_salaire';

    protected $fillable = [
        'school_id', 'personnel_id', 'montant', 'nombre_mois', 'mensualite', 'date_avance', 'motif', 'accordee_par',
        'annule_le', 'annule_par', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'date_avance' => 'date',
            'montant' => 'integer',
            'nombre_mois' => 'integer',
            'mensualite' => 'integer',
            'annule_le' => 'datetime',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Seules les avances non annulées comptent dans un solde ou une masse à recouvrer. */
    public function scopeValides(Builder $query): Builder
    {
        return $query->whereNull('annule_le');
    }

    public function estAnnulee(): bool
    {
        return $this->annule_le !== null;
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function accordeePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accordee_par');
    }

    public function remboursements(): HasMany
    {
        return $this->hasMany(AvanceRemboursement::class);
    }

    public function getMontantRembourseAttribute(): int
    {
        return (int) $this->remboursements->sum('montant');
    }

    public function getSoldeAttribute(): int
    {
        return max(0, $this->montant - $this->montant_rembourse);
    }

    public function getStatutAttribute(): string
    {
        return match (true) {
            $this->estAnnulee() => 'annulee',
            $this->solde === 0 => 'remboursee',
            $this->montant_rembourse > 0 => 'partielle',
            default => 'en_cours',
        };
    }
}
