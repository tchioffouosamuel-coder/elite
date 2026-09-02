<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Réception des DLR (Delivery Report) envoyés par Orange en callback HTTP
 * après l'envoi d'un SMS. Orange n'authentifie pas cet appel ("No
 * authentication" côté portail) et retente l'envoi si la réponse n'est pas
 * un 2xx : on répond donc toujours 200, même en cas de payload
 * incompréhensible, pour ne jamais être marqué "injoignable" et déclencher
 * des retries en boucle.
 */
class SmsCallbackController extends Controller
{
    public function handleDlr(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::channel('single')->info('[SMS][DLR] Callback Orange reçu', [
            'payload' => $payload,
            'headers' => $request->headers->all(),
        ]);

        try {
            $messageId = $this->extraireMessageId($payload);
            $statut = $this->extraireStatut($payload);
            $destinataire = $this->extraireDestinataire($payload);

            // Aiven peut mettre quelques centaines de ms à répondre juste
            // après un cold start Render : une seule tentative supplémentaire
            // suffit à absorber ce cas sans faire attendre Orange longtemps.
            retry(2, function () use ($messageId, $statut, $destinataire, $payload) {
                if ($messageId) {
                    SmsLog::updateOrCreate(
                        ['message_id' => $messageId],
                        [
                            'recipient' => $destinataire,
                            'status' => $statut,
                            'raw_payload' => $payload,
                        ],
                    );
                } else {
                    // Pas d'identifiant exploitable dans ce payload : on
                    // trace quand même la réception, pour ajuster le parsing
                    // une fois le format réel d'Orange connu.
                    SmsLog::create([
                        'message_id' => null,
                        'recipient' => $destinataire,
                        'status' => $statut,
                        'raw_payload' => $payload,
                    ]);
                }
            }, 200);
        } catch (\Throwable $e) {
            Log::error('[SMS][DLR] Échec de traitement du callback Orange : '.$e->getMessage(), [
                'payload' => $payload,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Le nom exact du champ varie selon les API Orange (SMS OneAPI vs SMS
     * Africa & Middle East) : on essaie les clés connues avant de fouiller
     * récursivement le payload.
     */
    private function extraireMessageId(array $payload): ?string
    {
        $candidats = [
            'message_id', 'messageId', 'messageID', 'id',
            'correlator', 'requestId', 'request_id',
        ];

        foreach ($candidats as $cle) {
            if (! empty($payload[$cle])) {
                return (string) $payload[$cle];
            }
        }

        // Format OneAPI : { "deliveryInfoNotification": { "deliveryInfo": { "resourceURL": ".../requests/{id}" } } }
        $resourceUrl = $payload['deliveryInfoNotification']['deliveryInfo']['resourceURL'] ?? null;
        if ($resourceUrl && preg_match('#/([^/]+)/deliveryInfos#', $resourceUrl, $m)) {
            return $m[1];
        }
        if ($resourceUrl && preg_match('#/requests/([^/]+)#', $resourceUrl, $m)) {
            return $m[1];
        }

        $trouve = $this->chercherRecursivement($payload, $candidats);

        return $trouve !== null ? (string) $trouve : null;
    }

    private function extraireDestinataire(array $payload): ?string
    {
        $candidats = ['recipient', 'to', 'destinationAddress', 'msisdn', 'address', 'phone_number'];

        foreach ($candidats as $cle) {
            if (! empty($payload[$cle])) {
                return $this->nettoyerAdresse((string) $payload[$cle]);
            }
        }

        $adresse = $payload['deliveryInfoNotification']['deliveryInfo']['address'] ?? null;
        if ($adresse) {
            return $this->nettoyerAdresse((string) $adresse);
        }

        $trouve = $this->chercherRecursivement($payload, $candidats);

        return $trouve !== null ? $this->nettoyerAdresse((string) $trouve) : null;
    }

    private function nettoyerAdresse(string $adresse): string
    {
        return preg_replace('/^tel:/i', '', $adresse);
    }

    private function extraireStatut(array $payload): string
    {
        $candidats = ['status', 'deliveryStatus', 'state', 'dlr_status'];

        $brut = null;
        foreach ($candidats as $cle) {
            if (! empty($payload[$cle])) {
                $brut = $payload[$cle];
                break;
            }
        }

        if ($brut === null) {
            $brut = $payload['deliveryInfoNotification']['deliveryInfo']['deliveryStatus']
                ?? $this->chercherRecursivement($payload, $candidats);
        }

        return $this->normaliserStatut($brut !== null ? (string) $brut : null);
    }

    private function normaliserStatut(?string $brut): string
    {
        if (! $brut) {
            return SmsLog::STATUT_INCONNU;
        }

        return match (strtolower($brut)) {
            'deliveredtoterminal', 'delivered', 'delivrd', 'success' => SmsLog::STATUT_LIVRE,
            'deliveryimpossible', 'failed', 'undeliverable', 'expired', 'rejected', 'deliveryuncertain' => SmsLog::STATUT_ECHEC,
            'messagewaiting', 'buffered', 'pending', 'sent', 'accepted', 'dispatched' => SmsLog::STATUT_EN_ATTENTE,
            default => SmsLog::STATUT_INCONNU,
        };
    }

    /**
     * Recherche en profondeur d'une des clés données, tant que le format
     * réel du payload Orange n'est pas figé.
     */
    private function chercherRecursivement(array $payload, array $cles): mixed
    {
        foreach ($payload as $cle => $valeur) {
            if (is_string($cle) && in_array($cle, $cles, true) && ! empty($valeur) && ! is_array($valeur)) {
                return $valeur;
            }

            if (is_array($valeur)) {
                $resultat = $this->chercherRecursivement($valeur, $cles);
                if ($resultat !== null) {
                    return $resultat;
                }
            }
        }

        return null;
    }
}
