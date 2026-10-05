<?php

namespace App\Models;

use App\Http\Middleware\JournaliserAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une requête API journalisée — qui, quand, quoi, depuis où, avec quel
 * résultat — cf. {@see JournaliserAudit}.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    public const ACTIONS = [
        'connexion' => 'Connexion',
        'connexion_echouee' => 'Connexion échouée',
        'deconnexion' => 'Déconnexion',
        'consultation' => 'Consultation',
        'creation' => 'Création',
        'modification' => 'Modification',
        'suppression' => 'Suppression',
        'import' => 'Import',
        'export' => 'Export',
        'impression' => 'Impression',
        'synchronisation' => 'Synchronisation',
        'action' => 'Action métier',
    ];

    protected $fillable = [
        'created_at',
        'school_id',
        'user_id',
        'user_nom',
        'user_role',
        'action',
        'module',
        'route',
        'methode',
        'url',
        'parametres',
        'donnees',
        'changements',
        'statut_http',
        'duree_ms',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'parametres' => 'array',
            'donnees' => 'array',
            'changements' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function scopeReussies(Builder $query): Builder
    {
        return $query->where('statut_http', '<', 400);
    }
}
