<?php

namespace App\Console\Commands;

use App\Models\DesktopProvisioning;
use App\Models\SyncOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pousse l'outbox locale (écritures faites hors-ligne) vers le serveur
 * distant, via l'endpoint générique déjà utilisé par le mobile
 * ({@see \App\Http\Controllers\Api\V1\SyncController::push()}).
 *
 * Chaque opération est rejouée indépendamment côté serveur (son contrôleur,
 * ses validations, ses privilèges) : ce n'est pas un simple recopiage de
 * ligne, contrairement à {@see SyncPull} qui applique des lignes déjà
 * validées par le serveur.
 */
class SyncPush extends Command
{
    /** Même plafond que `SyncController::LOT_PUSH_MAX` côté serveur distant. */
    private const LOT_MAX = 50;

    protected $signature = 'sync:push';

    protected $description = "Pousse l'outbox locale vers le serveur distant (client desktop)";

    public function handle(): int
    {
        $provisioning = DesktopProvisioning::actuelle();

        if ($provisioning === null) {
            $this->error('Aucune instance provisionnée : rien à pousser.');

            return self::FAILURE;
        }

        $lot = SyncOutbox::query()->enAttente()->limit(self::LOT_MAX)->get();

        if ($lot->isEmpty()) {
            $this->info('Rien à pousser.');

            return self::SUCCESS;
        }

        $reponse = Http::withToken($provisioning->token)
            ->baseUrl(rtrim($provisioning->serveur_url, '/').'/api/v1')
            ->acceptJson()
            ->post('sync', [
                'operations' => $lot->map(fn (SyncOutbox $o) => [
                    'id' => $o->id,
                    'methode' => $o->methode,
                    'chemin' => $o->chemin,
                    'corps' => $o->corps,
                ])->all(),
            ]);

        if ($reponse->failed()) {
            Log::warning('sync:push échec HTTP', ['statut' => $reponse->status()]);
            $this->error("Le serveur distant a répondu {$reponse->status()}.");

            return self::FAILURE;
        }

        $resultats = collect($reponse->json('data.resultats') ?? []);
        $reussies = 0;

        foreach ($resultats as $resultat) {
            // Chaque opération réussit ou échoue indépendamment côté serveur
            // (cf. SyncController::rejouer()) : une opération refusée reste
            // dans l'outbox — elle sera signalée à l'utilisateur plutôt que
            // silencieusement perdue — les autres avancent normalement.
            if (($resultat['statut'] ?? 500) < 300) {
                SyncOutbox::whereKey($resultat['id'])->update(['pushed_at' => now()]);
                $reussies++;
            } else {
                SyncOutbox::whereKey($resultat['id'])->increment('tentatives');
            }
        }

        $provisioning->update(['dernier_push_le' => now()]);

        $this->info("Push terminé : {$reussies}/{$lot->count()} opération(s) acceptée(s).");

        return self::SUCCESS;
    }
}
