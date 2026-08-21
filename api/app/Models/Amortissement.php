<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dotation d'un exercice sur un bien. Une seule par bien et par exercice. */
class Amortissement extends Model
{
    protected $fillable = ['immobilisation_id', 'annee_scolaire_id', 'montant', 'date_dotation'];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'date_dotation' => 'date'];
    }

    public function immobilisation(): BelongsTo
    {
        return $this->belongsTo(Immobilisation::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
