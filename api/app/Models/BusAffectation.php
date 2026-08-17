<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusAffectation extends Model
{
    protected $fillable = [
        'eleve_id', 'trajet_id', 'arret_id', 'annee_scolaire_id', 'tarif_mensuel', 'statut',
    ];

    protected function casts(): array
    {
        return ['tarif_mensuel' => 'integer'];
    }

    /** Une affectation suspendue ne compte plus dans l'effectif transporté du trajet. */
    public function scopeActives(Builder $query): Builder
    {
        return $query->where('statut', 'actif');
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function trajet(): BelongsTo
    {
        return $this->belongsTo(BusTrajet::class, 'trajet_id');
    }

    public function arret(): BelongsTo
    {
        return $this->belongsTo(BusArret::class, 'arret_id');
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
