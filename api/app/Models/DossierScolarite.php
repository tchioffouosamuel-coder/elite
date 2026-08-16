<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Situation financière d'un élève pour une année scolaire.
 *
 * C'est ici que se calcule ce que doit la famille. Les totaux ne sont pas
 * stockés : un montant figé en base finit toujours par diverger des
 * versements qu'il est censé résumer — il suffit d'une annulation de reçu
 * pour que le solde mente. Ils sont donc dérivés, et les écrans qui listent
 * des centaines d'élèves préchargent les relations plutôt que de recalculer
 * ligne à ligne (cf. `avecTotaux`).
 */
class DossierScolarite extends Model
{
    protected $table = 'dossiers_scolarite';

    protected $fillable = [
        'school_id',
        'annee_scolaire_id',
        'eleve_id',
        'montant_scolarite',
        'remise',
        'report_dette',
        'observation',
    ];

    protected function casts(): array
    {
        return [
            'montant_scolarite' => 'integer',
            'remise' => 'integer',
            'report_dette' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    /** Relations nécessaires au calcul des totaux, en une seule requête. */
    public function scopeAvecTotaux(Builder $query): Builder
    {
        return $query->with(['fraisAnnexes', 'versements' => fn ($q) => $q->valides()]);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function fraisAnnexes(): HasMany
    {
        return $this->hasMany(DossierFraisAnnexe::class);
    }

    public function versements(): HasMany
    {
        return $this->hasMany(Versement::class);
    }

    /** Scolarité nette de remise, hors frais annexes et hors reliquat antérieur. */
    public function getScolariteNetteAttribute(): int
    {
        return max(0, $this->montant_scolarite - $this->remise);
    }

    public function getTotalFraisAnnexesAttribute(): int
    {
        return (int) $this->fraisAnnexes->sum('montant');
    }

    /** Tout ce que la famille doit sur l'année, reliquat de l'an dernier compris. */
    public function getTotalDuAttribute(): int
    {
        return $this->scolarite_nette + $this->total_frais_annexes + $this->report_dette;
    }

    public function getTotalPayeAttribute(): int
    {
        return (int) $this->versements->whereNull('annule_le')->sum('montant');
    }

    /** Négatif en cas d'avance : la famille a versé plus que dû. */
    public function getSoldeAttribute(): int
    {
        return $this->total_du - $this->total_paye;
    }

    public function getResteAPayerAttribute(): int
    {
        return max(0, $this->solde);
    }

    /** Trop-perçu, à reporter ou à rembourser. */
    public function getAvanceAttribute(): int
    {
        return max(0, -$this->solde);
    }

    /**
     * `impaye` distingué de `partiel` à dessein : la relance n'est pas la même
     * pour une famille qui n'a rien versé et pour une qui s'acquitte par
     * tranches.
     */
    public function getStatutPaiementAttribute(): string
    {
        return match (true) {
            $this->total_du === 0 => 'sans_frais',
            $this->solde < 0 => 'avance',
            $this->solde === 0 => 'solde',
            $this->total_paye === 0 => 'impaye',
            default => 'partiel',
        };
    }

    public function getTauxRecouvrementAttribute(): float
    {
        return $this->total_du > 0 ? round($this->total_paye * 100 / $this->total_du, 2) : 0.0;
    }
}
