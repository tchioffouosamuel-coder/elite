<?php

namespace App\Support\Sync;

use App\Models\DesktopProvisioning;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Rafraîchissement du jeton d'accès d'un poste desktop provisionné, partagé
 * entre `sync:pull` et `sync:push` — le jeton d'accès n'est valable que 24h
 * (cf. `AuthService::ACCESS_TOKEN_TTL_MINUTES`), un poste resté ouvert (ou
 * simplement pas relancé depuis la veille) le voit expirer entre deux passages
 * de la boucle de synchronisation.
 */
trait RafraichitJetonDesktop
{
    /**
     * Échange le jeton de rafraîchissement (30 jours) contre une nouvelle
     * paire de jetons auprès du serveur distant — même mécanisme que
     * `AuthService::refresh()` côté web/mobile. Met à jour `$provisioning`
     * en base sur succès, pour que ce nouveau jeton serve à l'appel retenté
     * juste après comme aux suivants (l'autre commande, les prochains
     * passages de celle-ci).
     */
    private function rafraichirJeton(DesktopProvisioning $provisioning): bool
    {
        try {
            $reponse = Http::withToken($provisioning->refresh_token)
                ->baseUrl(rtrim($provisioning->serveur_url, '/').'/api/v1')
                ->acceptJson()
                ->connectTimeout(15)
                ->timeout(30)
                ->post('auth/refresh');
        } catch (\Illuminate\Http\Client\ConnectionException|\Illuminate\Http\Client\RequestException $e) {
            Log::warning('sync rafraîchissement du jeton impossible', [
                'user_id' => $provisioning->user_id,
                'erreur' => $e->getMessage(),
            ]);

            return false;
        }

        $donnees = $reponse->json('data') ?? [];

        if (! $reponse->successful() || ! isset($donnees['token'], $donnees['refresh_token'])) {
            Log::warning('sync rafraîchissement du jeton refusé', [
                'user_id' => $provisioning->user_id,
                'statut' => $reponse->status(),
            ]);

            return false;
        }

        $provisioning->update(['token' => $donnees['token'], 'refresh_token' => $donnees['refresh_token']]);

        return true;
    }
}
