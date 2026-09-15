<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Règle par défaut de preuve de présence pour « Ma journée », définie par
 * école et, en option, par sous-système. Une fiche de personnel peut la
 * surcharger (`Personnel::methode_validation_seance`) ; sans surcharge, cette
 * règle fait foi — voir `Seance::regleApplicable()`.
 */
class RegleValidationSeance extends Model
{
    protected $table = 'regles_validation_seances';

    protected $fillable = [
        'school_id',
        'sous_systeme_id',
        'methode_validation',
        'delai_valeur',
        'delai_unite',
    ];

    protected function casts(): array
    {
        return [
            'delai_valeur' => 'integer',
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

    public function sousSysteme(): BelongsTo
    {
        return $this->belongsTo(SousSysteme::class);
    }

    /** Délai converti en minutes, quelle que soit l'unité choisie. */
    public function delaiEnMinutes(): int
    {
        return match ($this->delai_unite) {
            'jours' => $this->delai_valeur * 1440,
            'semaines' => $this->delai_valeur * 10080,
            default => $this->delai_valeur,
        };
    }

    /**
     * Règle applicable pour une école (+ sous-système) : la plus spécifique
     * (école + sous-système) d'abord, puis celle définie pour toute l'école,
     * sinon aucune (l'appelant retombe alors sur le défaut historique).
     */
    public static function pour(int $schoolId, ?int $sousSystemeId): ?self
    {
        $regles = static::where('school_id', $schoolId)
            ->where(fn ($q) => $q->whereNull('sous_systeme_id')->when(
                $sousSystemeId !== null,
                fn ($q2) => $q2->orWhere('sous_systeme_id', $sousSystemeId)
            ))
            ->get();

        return ($sousSystemeId !== null ? $regles->firstWhere('sous_systeme_id', $sousSystemeId) : null)
            ?? $regles->firstWhere('sous_systeme_id', null);
    }
}
