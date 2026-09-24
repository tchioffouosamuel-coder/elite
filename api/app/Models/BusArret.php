<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusArret extends Model
{
    protected $fillable = [
        'trajet_id',
        'nom',
        'lieu_dit',
        'ordre',
        'heure_passage',
        'tarif_aller_simple',
        'tarif_retour_simple',
        'tarif_aller_retour',
    ];

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'tarif_aller_simple' => 'integer',
            'tarif_retour_simple' => 'integer',
            'tarif_aller_retour' => 'integer',
        ];
    }

    /**
     * Tarif de l'arrêt pour l'option choisie — c'est l'arrêt qui est facturé,
     * pas le trajet. Un arrêt sans prix pour cette option retombe sur la
     * grille de son trajet (arrêt créé par import, sans tarif renseigné).
     */
    public function tarifPour(string $option): ?int
    {
        $tarif = match ($option) {
            'aller_simple' => $this->tarif_aller_simple,
            'retour_simple' => $this->tarif_retour_simple,
            default => $this->tarif_aller_retour,
        };

        return $tarif ?? $this->trajet?->tarifPour($option);
    }

    public function trajet(): BelongsTo
    {
        return $this->belongsTo(BusTrajet::class, 'trajet_id');
    }
}
