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
 *
 * Plusieurs comptes pouvant être provisionnés sur le même poste, chaque lot
 * est rejoué séparément avec le jeton du compte auquel il appartient
 * (`sync_outbox.desktop_provisioning_id`, renseigné par
 * {@see \App\Http\Middleware\EnregistrerDansOutboxLocale} depuis le compte
 * authentifié au moment de l'écriture) — jamais un jeton unique choisi
 * arbitrairement.
 */
class SyncPush extends Command
{
    /** Même plafond que `SyncController::LOT_PUSH_MAX` côté serveur distant. */
    private const LOT_MAX = 50;

    protected $signature = 'sync:push';

    protected $description = "Pousse l'outbox locale vers le serveur distant (client desktop)";

    public function handle(): int
    {
        $provisionings = DesktopProvisioning::all();

        if ($provisionings->isEmpty()) {
            $this->error('Aucune instance provisionnée : rien à pousser.');

            return self::FAILURE;
        }

        // Écritures faites avant cette migration (colonne encore nulle) ou
        // par un compte depuis supprimé du poste : rejouées avec le premier
        // compte provisionné, à défaut de mieux — plutôt que de les laisser
        // bloquées dans l'outbox indéfiniment.
        $parDefaut = $provisionings->first();

        $echec = false;
        $rienATraiter = true;

        foreach ($provisionings as $provisioning) {
            $lot = SyncOutbox::query()->enAttente()
                ->where(function ($q) use ($provisioning, $parDefaut) {
                    $q->where('desktop_provisioning_id', $provisioning->id);
                    if ($provisioning->is($parDefaut)) {
                        $q->orWhereNull('desktop_provisioning_id');
                    }
                })
                ->limit(self::LOT_MAX)->get();

            if ($lot->isEmpty()) {
                continue;
            }

            $rienATraiter = false;

            $reponse = Http::withToken($provisioning->token)
                ->baseUrl(rtrim($provisioning->serveur_url, '/').'/api/v1')
                ->acceptJson()
                ->post('sync', [
                    'operations' => $lot->map(fn (SyncOutbox $o) => [
                        'id' => $o->id,
                        'methode' => $o->methode,
                        'chemin' => $o->chemin,
                        'school_id' => $o->school_id,
                        'corps' => $o->corps,
                    ])->all(),
                ]);

            if ($reponse->failed()) {
                Log::warning('sync:push échec HTTP', ['user_id' => $provisioning->user_id, 'statut' => $reponse->status()]);
                $this->error("Compte #{$provisioning->user_id} : le serveur distant a répondu {$reponse->status()}.");
                $echec = true;

                continue;
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

            $this->info("Compte #{$provisioning->user_id} : {$reussies}/{$lot->count()} opération(s) acceptée(s).");
        }

        if ($rienATraiter) {
            $this->info('Rien à pousser.');
        }

        return $echec ? self::FAILURE : self::SUCCESS;
    }
}
