<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Support\Telephone;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client de l'API SMS d'Orange (SMS Africa and Middle East) : récupération du
 * token OAuth2 et envoi d'un SMS via l'API "outbound". Chaque tentative
 * (réussie ou non) est tracée dans `sms_logs`, avec le `message_id` renvoyé
 * par Orange quand il est disponible — c'est ce même identifiant que
 * SmsCallbackController retrouvera plus tard dans le DLR pour mettre à jour
 * le statut de livraison.
 *
 * Volontairement une classe concrète plutôt qu'une façade : elle ne porte
 * aucun état global à exposer statiquement, et l'injection par constructeur
 * (comme partout ailleurs dans ce projet, cf. App\Services\Sms\SmsService)
 * la rend triviale à substituer par un mock ou un fake HTTP dans les tests.
 */
class OrangeSmsService
{
    private const CACHE_KEY_TOKEN = 'orange_sms_access_token';

    private const URL_TOKEN = 'https://api.orange.com/oauth/v3/token';

    private const URL_OUTBOUND = 'https://api.orange.com/smsmessaging/v1/outbound/';

    /**
     * @return string  le token d'accès Bearer, mis en cache 3500s (marge de
     *                 50s sous les 3600s annoncés par Orange, pour ne jamais
     *                 présenter un token expiré côté Orange).
     *
     * @throws \RuntimeException si le token ne peut pas être obtenu.
     */
    public function getAccessToken(): string
    {
        return Cache::remember(self::CACHE_KEY_TOKEN, 3500, function () {
            $clientId = config('services.orange.client_id');
            $clientSecret = config('services.orange.client_secret');
            $identifiants = base64_encode("{$clientId}:{$clientSecret}");

            $reponse = Http::asForm()
                ->withHeaders(['Authorization' => "Basic {$identifiants}"])
                ->timeout(15)
                ->post(self::URL_TOKEN, [
                    'grant_type' => 'client_credentials',
                ]);

            if ($reponse->failed() || ! $reponse->json('access_token')) {
                Log::error('[SMS][Orange] Échec de récupération du token OAuth2', [
                    'status' => $reponse->status(),
                    'body' => $reponse->body(),
                ]);

                throw new \RuntimeException('Impossible d\'obtenir un token Orange SMS.');
            }

            return $reponse->json('access_token');
        });
    }

    /**
     * Envoie un SMS via Orange. Ne lève jamais d'exception : toute erreur
     * (réseau, timeout, authentification, refus Orange) est loguée et
     * traduite en `success: false`, à charge de l'appelant de décider s'il
     * doit réagir — un envoi de confirmation raté ne doit jamais faire
     * échouer l'opération métier qui le déclenche.
     *
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendSms(string $to, string $message): array
    {
        $destinataire = $this->formaterDestinataire($to);

        try {
            $token = $this->getAccessToken();
        } catch (\Throwable $e) {
            $this->journaliser(null, $destinataire, 'failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }

        try {
            $reponse = $this->envoyerRequete($token, $destinataire, $message);

            // Le cache annonce un token encore valide mais Orange le refuse
            // (révocation anticipée, horloge décalée…) : on ne le fait
            // qu'une fois, pour ne jamais boucler indéfiniment sur un
            // identifiant client réellement invalide.
            if ($reponse->status() === 401) {
                Cache::forget(self::CACHE_KEY_TOKEN);
                $token = $this->getAccessToken();
                $reponse = $this->envoyerRequete($token, $destinataire, $message);
            }
        } catch (\Throwable $e) {
            Log::error("[SMS][Orange] Erreur réseau lors de l'envoi vers {$destinataire} : ".$e->getMessage());

            $this->journaliser(null, $destinataire, 'failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }

        $corps = $reponse->json() ?? [];
        $messageId = $this->extraireMessageId($corps);

        if ($reponse->failed()) {
            Log::error("[SMS][Orange] Échec d'envoi vers {$destinataire}", [
                'status' => $reponse->status(),
                'body' => $reponse->body(),
            ]);

            $this->journaliser($messageId, $destinataire, 'failed', $corps ?: ['raw' => $reponse->body()]);

            return ['success' => false, 'message_id' => $messageId, 'error' => $reponse->body()];
        }

        $this->journaliser($messageId, $destinataire, 'sent', $corps);

        return ['success' => true, 'message_id' => $messageId, 'error' => null];
    }

    private function envoyerRequete(string $token, string $destinataire, string $message): Response
    {
        $senderAddress = $this->formaterAdresse(config('services.orange.sender_address'));

        return Http::withToken($token)
            ->timeout(15)
            ->post(self::URL_OUTBOUND.rawurlencode($senderAddress).'/requests', [
                'outboundSMSMessageRequest' => [
                    'address' => $destinataire,
                    'senderAddress' => $senderAddress,
                    'senderName' => config('services.orange.sender_name'),
                    'outboundSMSTextMessage' => ['message' => $message],
                ],
            ]);
    }

    /**
     * `tel:+237XXXXXXXXX` : Orange exige ce préfixe, absent du format déjà
     * normalisé par App\Support\Telephone (partagé avec le reste de l'app).
     */
    private function formaterDestinataire(string $to): string
    {
        return 'tel:'.Telephone::normaliser($to);
    }

    /**
     * Même exigence de préfixe `tel:` côté expéditeur — Orange rejette la
     * requête ("SenderAddress is not starting with prefix tel:") si
     * ORANGE_SENDER_ADDRESS est renseigné sans, ce qui est la façon la plus
     * naturelle de le saisir dans le .env.
     */
    private function formaterAdresse(string $adresse): string
    {
        return str_starts_with($adresse, 'tel:') ? $adresse : 'tel:'.$adresse;
    }

    /**
     * L'identifiant du message n'est pas un champ dédié dans la réponse
     * Orange : il n'apparaît que comme dernier segment de `resourceURL`
     * (".../requests/{id}"). Même règle d'extraction que côté DLR
     * (cf. SmsCallbackController::extraireMessageId), pour que les deux
     * bouts se retrouvent sur le même identifiant.
     */
    private function extraireMessageId(array $corps): ?string
    {
        $resourceUrl = $corps['outboundSMSMessageRequest']['resourceURL'] ?? null;

        if ($resourceUrl && preg_match('#/requests/([^/]+)$#', (string) $resourceUrl, $m)) {
            return $m[1];
        }

        return null;
    }

    private function journaliser(?string $messageId, string $destinataire, string $statut, array $rawPayload): void
    {
        SmsLog::create([
            'message_id' => $messageId,
            'recipient' => $destinataire,
            'status' => $statut,
            'raw_payload' => $rawPayload,
        ]);
    }
}
