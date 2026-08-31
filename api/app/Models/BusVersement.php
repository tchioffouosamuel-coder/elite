<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Mensualité de transport scolaire réglée par une famille. Ne se supprime
 * pas : le reçu remis porte un numéro, seule une annulation tracée le
 * neutralise — même principe que {@see Versement} pour la scolarité.
 */
class BusVersement extends Model
{
    protected $fillable = [
        'school_id', 'bus_affectation_id', 'mois', 'numero_recu', 'date_versement', 'montant',
        'mode', 'reference_externe', 'encaisse_par', 'note',
        'annule_le', 'annule_par', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'mois' => 'date',
            'date_versement' => 'date',
            'annule_le' => 'datetime',
            'montant' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Seuls les versements non annulés comptent dans un solde ou une recette. */
    public function scopeValides(Builder $query): Builder
    {
        return $query->whereNull('annule_le');
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(BusAffectation::class, 'bus_affectation_id');
    }

    public function encaisseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encaisse_par');
    }

    /** Écritures du journal issues de cet encaissement, contrepassées à l'annulation. */
    public function ecritures(): MorphMany
    {
        return $this->morphMany(EcritureComptable::class, 'origine');
    }
}
