<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne de l'échéancier proposé à la demande, avant validation par un admin. */
class DemandeAvanceEcheance extends Model
{
    protected $fillable = ['demande_avance_salaire_id', 'mois', 'montant_prevu'];

    protected function casts(): array
    {
        return ['mois' => 'date', 'montant_prevu' => 'integer'];
    }

    public function demandeAvanceSalaire(): BelongsTo
    {
        return $this->belongsTo(DemandeAvanceSalaire::class);
    }
}
