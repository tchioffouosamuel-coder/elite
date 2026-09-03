<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un compte lié à ce poste desktop, avec le serveur distant dont il vient et
 * son mot de passe local (cf. {@see \App\Http\Controllers\Api\V1\DesktopProvisioningController::connexion()}).
 * Plusieurs comptes peuvent coexister sur le même poste — une ligne chacun —
 * chaque compte y réplique une ou plusieurs écoles ({@see ecoles()}),
 * chacune avec son propre avancement de synchronisation.
 */
class DesktopProvisioning extends Model
{
    protected $table = 'desktop_provisioning';

    protected $fillable = [
        'user_id', 'password', 'serveur_url', 'token', 'refresh_token',
        'dernier_push_le', 'provisionne_le',
    ];

    protected $hidden = ['password'];

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

    /**
     * Provisioning de ce compte précis sur ce poste, s'il existe — utilisé
     * pour la connexion locale et pour scoper `statutSync()`/`synchroniser()`
     * au compte réellement authentifié plutôt qu'à « le » poste.
     */
    public static function pourUtilisateur(int $userId): ?self
    {
        return static::where('user_id', $userId)->first();
    }
}
