<?php

namespace App\Console\Commands;

use App\Models\DesktopProvisioning;
use App\Models\SyncOutbox;
use App\Support\Sync\RafraichitJetonDesktop;
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
    use RafraichitJetonDesktop;

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
            $requeteLot = fn () => SyncOutbox::query()->enAttente()
                ->where(function ($q) use ($provisioning, $parDefaut) {
                    $q->where('desktop_provisioning_id', $provisioning->id);
                    if ($provisioning->is($parDefaut)) {
                        $q->orWhereNull('desktop_provisioning_id');
                    }
                })
                ->limit(self::LOT_MAX)->get();

            $lot = $requeteLot();

            if ($lot->isEmpty()) {
                continue;
            }

            $rienATraiter = false;
            $totalTraitees = 0;
            $totalReussies = 0;

            // Plusieurs lots successifs, pas un seul : une écriture hors-ligne
            // prolongée (panne réseau de plusieurs jours, gros import fait
            // localement) peut laisser des milliers d'opérations en attente —
            // n'en pousser que les 50 premières par passage de la boucle
            // périodique (toutes les 5 minutes) prendrait des heures à
            // rattraper. On draine ici tout ce qui est en attente au moment du
            // lancement, avec un plafond de sécurité pour ne jamais tourner
            // indéfiniment si le serveur distant se met à tout refuser (une
            // opération refusée n'obtient jamais `pushed_at`, donc resterait
            // "en attente" et referait indéfiniment partie du prochain lot).
            for ($passage = 0; $passage < 500; $passage++) {
                if ($lot->isEmpty()) {
                    break;
                }

                $reponse = $this->envoyerLot($provisioning, $lot);

                if ($reponse === null) {
                    $echec = true;

                    break;
                }

                $resultats = collect($reponse['resultats'] ?? []);
                $reussiesCePassage = 0;

                foreach ($resultats as $resultat) {
                    // Chaque opération réussit ou échoue indépendamment côté
                    // serveur (cf. SyncController::rejouer()) : une opération
                    // refusée reste dans l'outbox — elle sera signalée à
                    // l'utilisateur plutôt que silencieusement perdue — les
                    // autres avancent normalement.
                    if (($resultat['statut'] ?? 500) < 300) {
                        SyncOutbox::whereKey($resultat['id'])->update(['pushed_at' => now()]);
                        $reussiesCePassage++;
                    } else {
                        SyncOutbox::whereKey($resultat['id'])->increment('tentatives');
                    }
                }

                $totalTraitees += $lot->count();
                $totalReussies += $reussiesCePassage;

                // Un lot entièrement refusé (0 succès) n'avancerait jamais :
                // reprendre la même requête renverrait exactement les mêmes
                // lignes, encore en attente, à l'infini.
                if ($reussiesCePassage === 0) {
                    break;
                }

                $lot = $requeteLot();
            }

            $provisioning->update(['dernier_push_le' => now()]);

            $this->info("Compte #{$provisioning->user_id} : {$totalReussies}/{$totalTraitees} opération(s) acceptée(s).");
        }

        if ($rienATraiter) {
            $this->info('Rien à pousser.');
        }

        return $echec ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Envoie ce lot d'opérations, avec un rafraîchissement de jeton et un
     * seul nouvel essai si le serveur distant répond 401 — sans quoi, tant
     * que `sync:pull` n'a pas rafraîchi ce même jeton de son côté (même
     * table, mais un compte dormant sans pull réussi ne le déclenche
     * jamais), l'outbox reste bloquée indéfiniment sans qu'aucune erreur ne
     * remonte jusqu'à l'interface : observé en conditions réelles, « dernier
     * push » figé pendant que « dernier pull » continue d'avancer.
     *
     * Un 413 (lot trop volumineux pour le serveur distant) scinde le lot en
     * deux et retente chaque moitié séparément plutôt que d'abandonner tout
     * le monde : sans ce découpage, quelques imports de bibliothèque de
     * plusieurs Mo suffisaient à bloquer indéfiniment, dans le même lot,
     * une poignée d'opérations minuscules qui n'avaient rien à y voir —
     * observé en conditions réelles (21 opérations en attente, dont 14
     * imports totalisant ~18 Mo, aucune ne passait plus jamais).
     *
     * @param  \Illuminate\Support\Collection<int, SyncOutbox>  $lot
     * @return array{resultats: list<array{id: string, statut: int}>}|null
     */
    private function envoyerLot(DesktopProvisioning $provisioning, $lot, bool $jetonDejaRafraichi = false): ?array
    {
        try {
            $reponse = Http::withToken($provisioning->token)
                ->baseUrl(rtrim($provisioning->serveur_url, '/').'/api/v1')
                ->acceptJson()
                ->connectTimeout(30)
                ->timeout(60)
                // Un 413 est déterministe (la taille ne change pas d'un essai
                // à l'autre) : le retenter tel quel ne ferait que perdre 6
                // secondes avant d'arriver de toute façon au découpage
                // ci-dessous.
                ->retry(3, 3000, fn ($e) => ! ($e instanceof \Illuminate\Http\Client\RequestException && $e->response?->status() === 413))
                ->post('sync', [
                    'operations' => $lot->map(fn (SyncOutbox $o) => [
                        'id' => $o->id,
                        'methode' => $o->methode,
                        'chemin' => $o->chemin,
                        'school_id' => $o->school_id,
                        'corps' => $o->corps,
                    ])->all(),
                ]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $statut = $e->response?->status();

            if ($statut === 401 && ! $jetonDejaRafraichi && $this->rafraichirJeton($provisioning)) {
                return $this->envoyerLot($provisioning, $lot, jetonDejaRafraichi: true);
            }

            if ($statut === 413 && $lot->count() > 1) {
                $moitie = intdiv($lot->count(), 2);
                $premiere = $this->envoyerLot($provisioning, $lot->take($moitie));
                $seconde = $this->envoyerLot($provisioning, $lot->skip($moitie));

                return $premiere === null || $seconde === null
                    ? null
                    : ['resultats' => [...$premiere['resultats'], ...$seconde['resultats']]];
            }

            if ($statut === 413) {
                // Une seule opération, encore trop volumineuse : rien à
                // découper de plus. Elle restera dans l'outbox jusqu'à ce que
                // le serveur distant relève sa limite d'upload — la
                // retenter à chaque cycle ne change rien tant que ce n'est
                // pas fait, mais elle ne bloque plus les autres.
                Log::warning('sync:push opération trop volumineuse', [
                    'user_id' => $provisioning->user_id,
                    'operation_id' => $lot->first()->id,
                    'chemin' => $lot->first()->chemin,
                ]);
                $this->error("Compte #{$provisioning->user_id} : une opération dépasse la limite du serveur distant, restera en attente.");

                return ['resultats' => []];
            }

            Log::warning('sync:push échec HTTP', ['user_id' => $provisioning->user_id, 'statut' => $statut]);
            $this->error("Compte #{$provisioning->user_id} : le serveur distant a répondu {$statut}.");

            return null;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('sync:push erreur réseau', ['user_id' => $provisioning->user_id, 'erreur' => $e->getMessage()]);
            $this->error("Compte #{$provisioning->user_id} : erreur réseau, réessaiera au prochain sync.");

            return null;
        }

        return ['resultats' => $reponse->json('data.resultats') ?? []];
    }
}
