<?php

namespace App\Services\Push\Drivers;

use App\Services\Push\FcmCredentials;
use App\Services\Push\PushDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi via l'API HTTP v1 de Firebase Cloud Messaging.
 *
 * L'API v1 exige un jeton OAuth2 dérivé du compte de service, et n'accepte
 * qu'un destinataire par requête — d'où la boucle. Le jeton est mis en cache
 * pendant sa durée de vie plutôt que redemandé à chaque notification.
 */
class FcmPushDriver implements PushDriver
{
    public function envoyer(array $jetons, string $titre, string $message, array $donnees = []): int
    {
        $projet = config('services.fcm.projet');
        $acces = $this->jetonAcces();

        if (! $projet || ! $acces) {
            Log::warning('[Push] Firebase mal configuré, notification non envoyée.');

            return 0;
        }

        $envoyes = 0;
        $url = "https://fcm.googleapis.com/v1/projects/{$projet}/messages:send";

        foreach ($jetons as $jeton) {
            $reponse = Http::withToken($acces)->post($url, [
                'message' => [
                    'token' => $jeton,
                    'notification' => ['title' => $titre, 'body' => $message],
                    // FCM n'accepte que des chaînes dans `data` : un entier
                    // passé tel quel fait échouer tout le message.
                    'data' => array_map(fn ($v) => (string) $v, $donnees),
                    'android' => ['priority' => 'high'],
                ],
            ]);

            if ($reponse->successful()) {
                $envoyes++;

                continue;
            }

            /*
             * 404/403 : l'appareil a désinstallé l'app ou son jeton a été
             * révoqué. On le retire, sinon la table accumule indéfiniment des
             * destinataires morts qu'on retentera à chaque notification.
             */
            if (in_array($reponse->status(), [403, 404], true)) {
                \App\Models\DeviceToken::where('jeton', $jeton)->delete();
            }
        }

        return $envoyes;
    }

    /**
     * Jeton OAuth2 du compte de service, mis en cache un peu moins longtemps
     * que sa validité réelle (1 h) pour ne jamais l'utiliser expiré.
     */
    private function jetonAcces(): ?string
    {
        $chemin = FcmCredentials::chemin();

        if (! $chemin || ! is_file($chemin)) {
            return null;
        }

        return cache()->remember('fcm.jeton_acces', 3300, function () use ($chemin) {
            $compte = json_decode((string) file_get_contents($chemin), true);

            if (! is_array($compte) || ! isset($compte['client_email'], $compte['private_key'])) {
                return null;
            }

            $maintenant = time();
            $entete = ['alg' => 'RS256', 'typ' => 'JWT'];
            $charge = [
                'iss' => $compte['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $maintenant,
                'exp' => $maintenant + 3600,
            ];

            $base = $this->base64Url(json_encode($entete)).'.'.$this->base64Url(json_encode($charge));
            openssl_sign($base, $signature, $compte['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = $base.'.'.$this->base64Url($signature);

            $reponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $reponse->successful() ? $reponse->json('access_token') : null;
        });
    }

    private function base64Url(string $valeur): string
    {
        return rtrim(strtr(base64_encode($valeur), '+/', '-_'), '=');
    }
}
