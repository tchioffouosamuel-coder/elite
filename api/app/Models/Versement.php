<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Encaissement au comptoir. Ne se supprime pas : le recu remis a la famille
 * porte un numero, seule une annulation tracee le neutralise.
 */
class Versement extends Model
{
    protected $fillable = [
        'school_id', 'dossier_scolarite_id', 'numero_recu', 'date_versement', 'montant',
        'mode', 'reference_externe', 'encaisse_par', 'note',
        'annule_le', 'annule_par', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return ['date_versement' => 'date', 'annule_le' => 'datetime', 'montant' => 'integer'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Seuls les versements non annules comptent dans un solde ou une recette. */
    public function scopeValides(Builder $query): Builder
    {
        return $query->whereNull('annule_le');
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(DossierScolarite::class, 'dossier_scolarite_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(VersementLigne::class);
    }

    public function encaisseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encaisse_par');
    }

    /** Ecritures du journal issues de cet encaissement, contrepassees a l'annulation. */
    public function ecritures(): MorphMany
    {
        return $this->morphMany(EcritureComptable::class, 'origine');
    }
}
