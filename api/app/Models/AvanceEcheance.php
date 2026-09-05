<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne de l'échéancier planifié d'une avance : combien débiter, pour quel mois. */
class AvanceEcheance extends Model
{
    protected $fillable = ['avance_salaire_id', 'mois', 'montant_prevu'];

    protected function casts(): array
    {
        return ['mois' => 'date', 'montant_prevu' => 'integer'];
    }

    public function avanceSalaire(): BelongsTo
    {
        return $this->belongsTo(AvanceSalaire::class);
    }
}
