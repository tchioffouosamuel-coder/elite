<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi réel via l'API REST de Twilio. Appelée directement en HTTP plutôt que
 * via le SDK officiel : une seule requête, pas de dépendance Composer
 * supplémentaire à maintenir pour ça.
 */
class TwilioSmsDriver implements SmsDriver
{
    public function envoyer(string $telephone, string $message): bool
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (! $sid || ! $token || ! $from) {
            Log::warning("[SMS] Twilio non configuré (SID/TOKEN/FROM manquant) — message à {$telephone} non envoyé.");

            return false;
        }

        $reponse = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $telephone,
                'From' => $from,
                'Body' => $message,
            ]);

        if ($reponse->failed()) {
            Log::error("[SMS] Échec Twilio vers {$telephone} : ".$reponse->body());
        }

        return $reponse->successful();
    }
}
