<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Itinéraire rendu disponible par un chauffeur empêché (ou par la direction
 * pour lui), et sa reprise éventuelle par un collègue. Porté par le véhicule
 * plutôt que par un trajet : l'itinéraire d'un chauffeur, c'est le circuit de
 * son bus — tous ses trajets et arrêts.
 */
class BusRemplacement extends Model
{
    protected $fillable = [
        'vehicule_id',
        'chauffeur_titulaire_id',
        'chauffeur_remplacant_id',
        'du',
        'au',
        'motif',
        'statut',
        'ouvert_par',
        'pourvu_le',
        'annule_le',
    ];

    protected function casts(): array
    {
        return [
            'du' => 'date:Y-m-d',
            'au' => 'date:Y-m-d',
            'pourvu_le' => 'datetime',
            'annule_le' => 'datetime',
        ];
    }

    /** Ni annulé, ni périmé : ce qui pèse encore sur la conduite du véhicule. */
    public function scopeEnCours(Builder $query, ?string $date = null): Builder
    {
        $jour = $date ?? Carbon::today()->toDateString();

        return $query->where('statut', '!=', 'annule')
            ->whereDate('du', '<=', $jour)
            ->whereDate('au', '>=', $jour);
    }

    /** Offert, sans preneur — ce qu'un autre chauffeur peut reprendre. */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('statut', 'disponible')->whereNull('chauffeur_remplacant_id');
    }

    public function couvre(string $date): bool
    {
        return $this->statut !== 'annule'
            && $this->du->toDateString() <= $date
            && $this->au->toDateString() >= $date;
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(BusVehicule::class, 'vehicule_id');
    }

    public function titulaire(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'chauffeur_titulaire_id');
    }

    public function remplacant(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'chauffeur_remplacant_id');
    }

    public function ouvreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ouvert_par');
    }
}
