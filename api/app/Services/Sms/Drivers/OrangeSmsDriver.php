<?php

namespace App\Services\Sms\Drivers;

use App\Services\OrangeSmsService;
use App\Services\Sms\SmsDriver;

/**
 * Adapte OrangeSmsService (qui renvoie un tableau détaillé avec message_id,
 * pour la corrélation ultérieure avec le DLR) à l'interface SmsDriver
 * (simple bool) attendue par SmsService — permet de brancher Orange sans
 * toucher aux appelants existants (ScolariteController, BusService…).
 */
class OrangeSmsDriver implements SmsDriver
{
    public function envoyer(string $telephone, string $message): bool
    {
        return app(OrangeSmsService::class)->sendSms($telephone, $message)['success'];
    }
}
