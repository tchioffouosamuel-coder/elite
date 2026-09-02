<?php

namespace App\Services\Sms;

use App\Services\Sms\Drivers\LogSmsDriver;
use App\Services\Sms\Drivers\OrangeSmsDriver;
use App\Services\Sms\Drivers\TwilioSmsDriver;
use App\Support\Telephone;

/**
 * Point d'entrée unique pour l'envoi de SMS (confirmations de paiement,
 * alertes bus…). Le driver réel dépend de `services.sms.driver` : `log` en
 * développement tant qu'aucun compte n'est configuré, `twilio` en
 * production. Un appelant n'a jamais à savoir lequel est actif.
 */
class SmsService
{
    private SmsDriver $driver;

    public function __construct()
    {
        $this->driver = match (config('services.sms.driver', 'log')) {
            'twilio' => new TwilioSmsDriver,
            'orange' => new OrangeSmsDriver,
            default => new LogSmsDriver,
        };
    }

    /**
     * Envoie un SMS. Les échecs sont avalés (retournés en `false`, jamais
     * levés) : une notification est un service annexe, elle ne doit jamais
     * faire échouer l'opération métier qui la déclenche (encaissement,
     * arrêté de paie…).
     */
    public function envoyer(?string $telephone, string $message): bool
    {
        if (! $telephone) {
            return false;
        }

        return $this->driver->envoyer(Telephone::normaliser($telephone), $message);
    }
}
