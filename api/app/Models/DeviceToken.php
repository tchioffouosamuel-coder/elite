<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    public const PLATEFORMES = ['android', 'ios'];

    protected $fillable = ['user_id', 'jeton', 'plateforme', 'school_id', 'derniere_utilisation'];

    protected function casts(): array
    {
        return ['derniere_utilisation' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
