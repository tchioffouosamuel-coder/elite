<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TuteurTelephone extends Model
{
    protected $fillable = ['tuteur_id', 'numero', 'is_principal'];

    protected $casts = ['is_principal' => 'boolean'];

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(Tuteur::class);
    }
}
