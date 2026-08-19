<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Journal : une ligne par mouvement, quelle que soit la piece d'origine. */
class EcritureComptable extends Model
{
    // Laravel devinerait « ecriture_comptables » : le pluriel porte sur le premier mot.
    protected $table = 'ecritures_comptables';

    protected $fillable = [
        'school_id', 'annee_scolaire_id', 'date_ecriture', 'libelle', 'montant', 'sens',
        'compte_comptable_id', 'origine_type', 'origine_id',
    ];

    protected function casts(): array
    {
        return ['date_ecriture' => 'date', 'montant' => 'integer'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteComptable::class, 'compte_comptable_id');
    }

    public function origine(): MorphTo
    {
        return $this->morphTo();
    }
}
