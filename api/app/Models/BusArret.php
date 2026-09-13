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
    ];

    protected function casts(): array
    {
        return ['ordre' => 'integer'];
    }

    public function trajet(): BelongsTo
    {
        return $this->belongsTo(BusTrajet::class, 'trajet_id');
    }
}
