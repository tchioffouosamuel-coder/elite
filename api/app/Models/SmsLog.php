<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Trace d'un SMS sortant (Orange, Twilio…) et de son statut de livraison tel
 * que rapporté par le fournisseur via callback DLR.
 */
class SmsLog extends Model
{
    protected $fillable = [
        'message_id',
        'recipient',
        'status',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    public const STATUT_EN_ATTENTE = 'pending';

    public const STATUT_LIVRE = 'delivered';

    public const STATUT_ECHEC = 'failed';

    public const STATUT_INCONNU = 'unknown';
}
