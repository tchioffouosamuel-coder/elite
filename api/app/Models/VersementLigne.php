<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ventilation d'un versement entre scolarite, frais annexes et reliquat anterieur. */
class VersementLigne extends Model
{
    protected $fillable = ['versement_id', 'affectation', 'dossier_frais_annexe_id', 'libelle', 'montant'];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    public function versement(): BelongsTo
    {
        return $this->belongsTo(Versement::class);
    }

    public function fraisAnnexe(): BelongsTo
    {
        return $this->belongsTo(DossierFraisAnnexe::class, 'dossier_frais_annexe_id');
    }
}
