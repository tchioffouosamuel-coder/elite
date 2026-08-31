<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ligne unique décrivant à quel compte et quel serveur distant cette
 * instance locale (client desktop) est liée — un compte peut y répliquer
 * plusieurs écoles ({@see ecoles()}), chacune avec son propre avancement de
 * synchronisation. Voir {@see \App\Http\Controllers\Api\V1\DesktopProvisioningController}.
 */
class DesktopProvisioning extends Model
{
    protected $table = 'desktop_provisioning';

    protected $fillable = [
        'user_id', 'serveur_url', 'token', 'refresh_token',
        'dernier_push_le', 'provisionne_le',
    ];

    protected function casts(): array
    {
        return [
            'dernier_push_le' => 'datetime',
            'provisionne_le' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ecoles(): HasMany
    {
        return $this->hasMany(DesktopProvisioningEcole::class);
    }

    /** Une seule ligne existe jamais : le poste n'est lié qu'à un seul compte. */
    public static function actuelle(): ?self
    {
        return static::query()->first();
    }
}
