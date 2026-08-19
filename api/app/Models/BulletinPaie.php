<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bulletin de paie mensuel.
 *
 * Les totaux sont stockés, contrairement au dossier de scolarité : un bulletin
 * est arrêté à une date, sur des taux et un barème qui changeront. Le
 * recalculer plus tard donnerait un autre résultat que celui remis à l'agent
 * et déclaré à la CNPS. Seul un brouillon se recalcule.
 */
class BulletinPaie extends Model
{
    protected $table = 'bulletins_paie';

    protected $fillable = [
        'school_id', 'personnel_id', 'annee_scolaire_id', 'numero',
        'annee', 'mois', 'periode_debut', 'periode_fin',
        'jours_ouvrables', 'jours_travailles',
        'salaire_brut', 'net_taxable', 'charges_salariales', 'charges_patronales',
        'deduction_absences', 'deduction_raff', 'deduction_njangi', 'deduction_pret', 'deduction_autre',
        'net_a_payer', 'statut', 'mode_paiement', 'date_paiement', 'valide_par',
        'emarge_le', 'emargement_reference',
    ];

    protected function casts(): array
    {
        return [
            'periode_debut' => 'date',
            'periode_fin' => 'date',
            'date_paiement' => 'date',
            'emarge_le' => 'datetime',
            'annee' => 'integer',
            'mois' => 'integer',
            'jours_ouvrables' => 'integer',
            'jours_travailles' => 'integer',
            'salaire_brut' => 'integer',
            'net_taxable' => 'integer',
            'charges_salariales' => 'integer',
            'charges_patronales' => 'integer',
            'deduction_absences' => 'integer',
            'deduction_raff' => 'integer',
            'deduction_njangi' => 'integer',
            'deduction_pret' => 'integer',
            'deduction_autre' => 'integer',
            'net_a_payer' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Bulletins qui pèsent sur la masse salariale : un brouillon n'est pas un engagement. */
    public function scopeArretes(Builder $query): Builder
    {
        return $query->whereIn('statut', ['valide', 'paye']);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(BulletinPaieLigne::class)->orderBy('ordre');
    }

    public function gains(): HasMany
    {
        return $this->lignes()->where('type', 'gain');
    }

    public function retenues(): HasMany
    {
        return $this->lignes()->where('type', 'retenue');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function estModifiable(): bool
    {
        return $this->statut === 'brouillon';
    }

    /** Total des déductions hors barème légal (raff, njangi, prêt, absences…). */
    public function getTotalDeductionsAttribute(): int
    {
        return $this->deduction_absences + $this->deduction_raff + $this->deduction_njangi
            + $this->deduction_pret + $this->deduction_autre;
    }

    /** Ce que l'opération coûte réellement à l'établissement, part patronale comprise. */
    public function getCoutEmployeurAttribute(): int
    {
        return $this->salaire_brut + $this->charges_patronales;
    }

    public function getPeriodeLibelleAttribute(): string
    {
        $mois = [
            1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
        ];

        return ($mois[$this->mois] ?? (string) $this->mois).' '.$this->annee;
    }
}
