<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Plan de comptes du complexe, repris du classeur comptable de l'etablissement. */
class CompteComptable extends Model
{
    // Laravel devinerait « compte_comptables » : le pluriel porte sur le premier mot.
    protected $table = 'comptes_comptables';

    protected $fillable = ['code', 'libelle', 'libelle_en', 'classe', 'sens', 'is_active'];

    protected function casts(): array
    {
        return ['classe' => 'integer', 'is_active' => 'boolean'];
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }

    public function ecritures(): HasMany
    {
        return $this->hasMany(EcritureComptable::class);
    }
}
