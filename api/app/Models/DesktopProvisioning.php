<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne unique décrivant à quel compte et quel serveur distant cette
 * instance locale (client desktop) est liée. Voir
 * {@see \App\Http\Controllers\Api\V1\DesktopProvisioningController}.
 */
class DesktopProvisioning extends Model
{
    protected $table = 'desktop_provisioning';

    protected $fillable = [
        'user_id', 'school_id', 'serveur_url', 'token', 'refresh_token',
        'curseur_sync', 'dernier_pull_le', 'dernier_push_le', 'provisionne_le',
    ];

    protected function casts(): array
    {
        return [
            'dernier_pull_le' => 'datetime',
            'dernier_push_le' => 'datetime',
            'provisionne_le' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Une seule ligne existe jamais : le poste n'est lié qu'à un seul compte. */
    public static function actuelle(): ?self
    {
        return static::query()->first();
    }
}
