<?php

namespace App\Services\Whatsapp\Drivers;

use App\Services\Whatsapp\WhatsappDriver;
use Illuminate\Support\Facades\Log;

/**
 * Driver par défaut tant qu'aucun compte WhatsApp Business (Meta Cloud API,
 * Twilio…) n'est configuré : le message s'écrit dans les logs au lieu de
 * partir réellement. Permet de développer et de vérifier le déclenchement des
 * notifications sans dépendre d'un compte actif.
 */
class LogWhatsappDriver implements WhatsappDriver
{
    public function envoyer(string $telephone, string $message): bool
    {
        Log::info("[WhatsApp simulé] à {$telephone} : {$message}");

        return true;
    }
}
