<?php

namespace App\Console\Commands;

use App\Models\SyncFichierEnAttente;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vide la file de fichiers en attente ({@see SyncFichierEnAttente}), alimentée
 * par {@see \App\Console\Commands\SyncPull}, en téléchargeant les fichiers par
 * lots concurrents plutôt qu'un par un — c'est cette étape, plus lente que le
 * reste du clonage, qu'on laisse désormais tourner en tâche de fond
 * (`main.cjs`) après un premier clonage plutôt que de bloquer dessus.
 *
 * N'a jamais besoin de connaître le provisioning ou l'école d'origine : la
 * file porte déjà tout ce qu'il faut (`chemin` + `serveur_url`), exactement
 * comme le faisait l'ancien téléchargement synchrone dans `SyncPull`.
 */
class SyncFichiers extends Command
{
    protected $signature = 'sync:fichiers {--lot=15 : Nombre de téléchargements menés en parallèle par page}';

    protected $description = 'Télécharge les fichiers (photos, justificatifs…) mis en attente par sync:pull';

    public function handle(): int
    {
        $taillelot = max(1, (int) $this->option('lot'));
        $total = 0;
        $echecs = 0;

        // Toute la file présente au lancement, pas un flux continu : un
        // fichier mis en attente par un `sync:pull` démarré PENDANT cette
        // commande sera traité au prochain passage (périodique, toutes les 5
        // minutes) plutôt que de faire tourner celle-ci indéfiniment.
        SyncFichierEnAttente::query()
            ->orderBy('id')
            ->chunkById($taillelot, function ($lot) use (&$total, &$echecs) {
                $reponses = Http::pool(fn ($pool) => $lot->map(
                    fn (SyncFichierEnAttente $fichier) => $pool->as($fichier->id)
                        ->baseUrl(rtrim($fichier->serveur_url, '/'))
                        ->connectTimeout(10)
                        ->timeout(30)
                        ->get('storage/'.$fichier->chemin)
                ));

                foreach ($lot as $fichier) {
                    $reponse = $reponses[$fichier->id] ?? null;
                    $total++;

                    if (! $reponse instanceof Response || ! $reponse->successful()) {
                        $echecs++;
                        Log::warning('sync:fichiers échec', [
                            'chemin' => $fichier->chemin,
                            'erreur' => $reponse instanceof \Throwable ? $reponse->getMessage() : $reponse?->status(),
                        ]);
                        // Laissé en file : un aléa réseau isolé se retentera
                        // au prochain passage, exactement comme le faisait
                        // l'ancien téléchargement inline dans `sync:pull`.
                        continue;
                    }

                    $destination = storage_path('app/public/'.$fichier->chemin);

                    if (! is_dir(dirname($destination))) {
                        mkdir(dirname($destination), 0755, true);
                    }

                    file_put_contents($destination, $reponse->body());
                    $fichier->delete();
                }
            });

        $this->info("{$total} fichier(s) traité(s), {$echecs} échec(s) (retenteront au prochain passage).");

        return self::SUCCESS;
    }
}
