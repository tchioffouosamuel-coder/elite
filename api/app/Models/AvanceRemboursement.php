<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une tranche remboursée sur une avance sur salaire — retenue mensuelle ou versement direct. */
class AvanceRemboursement extends Model
{
    protected $fillable = ['avance_salaire_id', 'montant', 'date_remboursement', 'mode', 'note'];

    protected function casts(): array
    {
        return ['date_remboursement' => 'date', 'montant' => 'integer'];
    }

    public function avanceSalaire(): BelongsTo
    {
        return $this->belongsTo(AvanceSalaire::class);
    }
}
