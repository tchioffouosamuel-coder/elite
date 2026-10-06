<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pointage d'un enfant monté à son arrêt, pour une tournée donnée (une date
 * et un sens). L'existence de la ligne est l'information : pas de ligne =
 * pas encore pris. Décocher supprime — cf. la migration
 * `create_bus_chauffeur_tables`.
 */
class BusRamassage extends Model
{
    public const SENS = ['aller', 'retour'];

    protected $fillable = [
        'bus_affectation_id',
        'arret_id',
        'date_ramassage',
        'sens',
        'pris_le',
        'pointe_par',
    ];

    protected function casts(): array
    {
        return [
            'date_ramassage' => 'date:Y-m-d',
            'pris_le' => 'datetime',
        ];
    }

    /** La tournée d'un jour et d'un sens — le filtre de tout écran de ramassage. */
    public function scopeTournee(Builder $query, string $date, string $sens): Builder
    {
        return $query->whereDate('date_ramassage', $date)->where('sens', $sens);
    }

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(BusAffectation::class, 'bus_affectation_id');
    }

    public function arret(): BelongsTo
    {
        return $this->belongsTo(BusArret::class, 'arret_id');
    }

    public function pointeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pointe_par');
    }
}
