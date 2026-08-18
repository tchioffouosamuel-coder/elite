<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Trace d'une ligne supprimée, consommée par la synchronisation mobile
 * (cf. `RegistreSync`). En écriture seule côté application : rien ne modifie
 * une pierre tombale, on ne fait que l'ajouter puis la lire.
 */
class SyncTombstone extends Model
{
    public $timestamps = false;

    protected $fillable = ['entite', 'entite_id', 'school_id', 'supprime_le'];

    protected function casts(): array
    {
        return ['supprime_le' => 'datetime'];
    }
}
