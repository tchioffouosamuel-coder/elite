<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catalogue des frais annexes : les « accessoires » du recu de l'etablissement. */
class FraisAnnexe extends Model
{
    protected $table = 'frais_annexes';

    protected $fillable = ['school_id', 'annee_scolaire_id', 'libelle', 'montant', 'obligatoire', 'is_active'];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'obligatoire' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
