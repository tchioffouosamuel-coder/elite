<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Log;

/**
 * Driver par défaut tant qu'aucun compte Twilio n'est configuré : le message
 * s'écrit dans les logs au lieu de partir réellement. Permet de développer et
 * de vérifier le déclenchement des notifications sans dépendre d'un compte
 * SMS actif ni consommer de crédit.
 */
class LogSmsDriver implements SmsDriver
{
    public function envoyer(string $telephone, string $message): bool
    {
        Log::info("[SMS simulé] à {$telephone} : {$message}");

        return true;
    }
}
