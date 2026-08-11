<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    /** Motifs relevés à l'appel ; seule une absence en porte un. */
    public const MOTIFS = ['maladie', 'inconnu', 'scolarite', 'permission'];

    protected $fillable = ['seance_id', 'eleve_id', 'statut', 'motif', 'justifie', 'remarque'];

    protected function casts(): array
    {
        return ['justifie' => 'boolean'];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
}
