<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Frais annexe reellement du par un eleve ; le montant est recopie du catalogue. */
class DossierFraisAnnexe extends Model
{
    protected $fillable = ['dossier_scolarite_id', 'frais_annexe_id', 'libelle', 'montant'];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(DossierScolarite::class, 'dossier_scolarite_id');
    }

    public function fraisAnnexe(): BelongsTo
    {
        return $this->belongsTo(FraisAnnexe::class);
    }
}
