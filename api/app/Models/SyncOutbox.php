<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une écriture faite sur l'instance locale (desktop), en attente de rejeu
 * vers le serveur distant — voir {@see \App\Http\Middleware\EnregistrerDansOutboxLocale}
 * (alimentation) et {@see \App\Console\Commands\SyncPush} (vidage).
 */
class SyncOutbox extends Model
{
    protected $table = 'sync_outbox';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['id', 'methode', 'chemin', 'school_id', 'desktop_provisioning_id', 'corps', 'created_at', 'pushed_at', 'tentatives'];

    protected function casts(): array
    {
        return [
            'corps' => 'array',
            'created_at' => 'datetime',
            'pushed_at' => 'datetime',
        ];
    }

    public function scopeEnAttente($query)
    {
        return $query->whereNull('pushed_at')->orderBy('created_at');
    }
}
