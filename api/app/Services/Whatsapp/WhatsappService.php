<?php

namespace App\Services\Whatsapp;

use App\Services\Whatsapp\Drivers\LogWhatsappDriver;
use App\Support\Telephone;

/**
 * Point d'entrée unique pour l'envoi de WhatsApp (confirmations de paiement…).
 * Le driver réel dépend de `services.whatsapp.driver` : `log` tant qu'aucun
 * compte WhatsApp Business n'est configuré. Un appelant n'a jamais à savoir
 * lequel est actif — même contrat que {@see \App\Services\Sms\SmsService}.
 */
class WhatsappService
{
    private WhatsappDriver $driver;

    public function __construct()
    {
        $this->driver = match (config('services.whatsapp.driver', 'log')) {
            default => new LogWhatsappDriver,
        };
    }

    /**
     * Envoie un message WhatsApp. Les échecs sont avalés (retournés en
     * `false`, jamais levés) : une notification est un service annexe, elle
     * ne doit jamais faire échouer l'opération métier qui la déclenche
     * (encaissement…).
     */
    public function envoyer(?string $telephone, string $message): bool
    {
        if (! $telephone) {
            return false;
        }

        return $this->driver->envoyer(Telephone::normaliser($telephone), $message);
    }
}
