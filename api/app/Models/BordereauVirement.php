<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Numéro d'ordre du bordereau de virement d'un mois donné — pas le bordereau
 * lui-même, recalculé à la volée depuis les bulletins arrêtés
 * ({@see \App\Services\Paie\BordereauVirementService}), mais le numéro qu'il
 * porte : il doit rester le même si le PDF est régénéré plusieurs fois avant
 * l'envoi à la banque.
 */
class BordereauVirement extends Model
{
    protected $table = 'bordereaux_virement';

    protected $fillable = ['school_id', 'annee', 'mois', 'numero'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Numéro d'ordre affiché sur le document, complété à 3 chiffres (001, 002…) — comme DocumentReference. */
    public function numeroFormate(): string
    {
        return str_pad((string) $this->numero, 3, '0', STR_PAD_LEFT);
    }
}
