<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une école répliquée sur ce poste, avec son propre curseur de
 * synchronisation. Voir {@see DesktopProvisioning::ecoles()}.
 */
class DesktopProvisioningEcole extends Model
{
    protected $table = 'desktop_provisioning_ecoles';

    protected $fillable = ['desktop_provisioning_id', 'school_id', 'curseur_sync', 'dernier_pull_le'];

    protected function casts(): array
    {
        return ['dernier_pull_le' => 'datetime'];
    }

    public function provisioning(): BelongsTo
    {
        return $this->belongsTo(DesktopProvisioning::class, 'desktop_provisioning_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
