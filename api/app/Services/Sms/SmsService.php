<?php

namespace App\Services\Sms;

use App\Services\Sms\Drivers\LogSmsDriver;
use App\Services\Sms\Drivers\TwilioSmsDriver;

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

        return $this->driver->envoyer($this->normaliser($telephone), $message);
    }

    /**
     * Numéro camerounais local (ex. « 692 34 21 06 ») vers le format E.164
     * qu'attend Twilio (+237692342106). Un numéro déjà international
     * (commençant par +) n'est pas retouché.
     */
    private function normaliser(string $telephone): string
    {
        $nettoye = preg_replace('/[^\d+]/', '', $telephone) ?? $telephone;

        if (str_starts_with($nettoye, '+')) {
            return $nettoye;
        }

        // Les numéros locaux à 9 chiffres commencent par 6 (mobile) ou 2
        // (fixe) ; un 0 initial est un préfixe national à retirer.
        $nettoye = ltrim($nettoye, '0');

        return '+237'.$nettoye;
    }
}
