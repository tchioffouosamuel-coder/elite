<?php

namespace App\Console\Commands;

use App\Models\DesktopProvisioning;
use App\Models\SyncTombstone;
use App\Support\Sync\RegistreSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tire les changements du serveur distant vers la base locale (client
 * desktop offline). Réutilise tel quel le protocole déjà en place pour le
 * mobile ({@see \App\Http\Controllers\Api\V1\SyncController::pull()}) :
 * cette instance locale se comporte ici comme n'importe quel client sync.
 *
 * Résolution de conflit : le plus récent gagne. Une ligne locale plus
 * récente que la ligne distante reçue n'est PAS écrasée — elle n'a pas
 * encore été poussée (cf. {@see SyncPush}), l'écraser perdrait une
 * modification faite hors-ligne.
 */
class SyncPull extends Command
{
    protected $signature = 'sync:pull';

    protected $description = "Tire les données du serveur distant vers la base locale (client desktop)";

    public function handle(): int
    {
        $provisioning = DesktopProvisioning::actuelle();

        if ($provisioning === null) {
            $this->error('Aucune instance provisionnée : rien à synchroniser.');

            return self::FAILURE;
        }

        $entitesParCle = RegistreSync::entites();
        $complet = false;
        $curseur = $provisioning->curseur_sync;
        $totalLignes = 0;
        $totalSuppressions = 0;

        // Les lignes reçues n'arrivent pas dans un ordre qui respecte les
        // dépendances (une classe peut suivre l'élève qui la référence, un
        // élève peut précéder son école) — le registre expose plus de
        // quarante entités inter-dépendantes, les trier correctement à
        // chaque page serait fragile face au moindre nouveau lien. Le jeu de
        // données reçu est par construction cohérent (il vient du serveur de
        // référence) : désactiver la vérification le temps de l'appliquer
        // n'introduit aucune incohérence, ça évite seulement d'exiger un
        // ordre que SQLite, contrairement à MySQL, fait strictement
        // respecter à l'insertion.
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            while (! $complet) {
                $reponse = Http::withToken($provisioning->token)
                    ->baseUrl(rtrim($provisioning->serveur_url, '/').'/api/v1')
                    ->acceptJson()
                    ->get('sync', array_filter(['depuis' => $curseur]));

                if ($reponse->failed()) {
                    Log::warning('sync:pull échec HTTP', ['statut' => $reponse->status()]);
                    $this->error("Le serveur distant a répondu {$reponse->status()}.");

                    return self::FAILURE;
                }

                $payload = $reponse->json('data') ?? [];

                foreach ((array) ($payload['donnees'] ?? []) as $cle => $lignes) {
                    if (! isset($entitesParCle[$cle])) {
                        continue;
                    }

                    $modele = $entitesParCle[$cle]['modele'];

                    foreach ($lignes as $ligne) {
                        $this->appliquerLigne($modele, $ligne);
                        $totalLignes++;
                    }
                }

                foreach ((array) ($payload['suppressions'] ?? []) as $suppression) {
                    $cle = $suppression['entite'] ?? null;

                    if ($cle !== null && isset($entitesParCle[$cle])) {
                        $entitesParCle[$cle]['modele']::query()->whereKey($suppression['id'])->delete();
                        $totalSuppressions++;
                    }
                }

                $curseur = $payload['curseur'] ?? $curseur;
                $complet = (bool) ($payload['complet'] ?? true);
            }
        } finally {
            // Toujours réactivée, même sur un retour anticipé (échec HTTP) ou
            // une exception : le reste de l'application (écritures normales
            // de l'utilisateur) doit retrouver l'intégrité référentielle
            // habituelle dès que ce lot est traité.
            DB::statement('PRAGMA foreign_keys = ON');
        }

        $provisioning->update(['curseur_sync' => $curseur, 'dernier_pull_le' => now()]);

        $this->info("Pull terminé : {$totalLignes} ligne(s), {$totalSuppressions} suppression(s).");

        return self::SUCCESS;
    }

    /**
     * Upsert d'une ligne reçue, avec la règle du plus récent qui gagne.
     *
     * @param  class-string  $modele
     */
    private function appliquerLigne(string $modele, array $ligne): void
    {
        if (! isset($ligne['id'])) {
            return;
        }

        $existante = $modele::query()->find($ligne['id']);

        // La ligne distante ne porte pas forcément `updated_at` (colonnes
        // projetées par `RegistreSync`) : sans base de comparaison, on
        // applique — c'est le cas d'une création locale jamais vue avant.
        if ($existante !== null && isset($ligne['updated_at'], $existante->updated_at)
            && $existante->updated_at->gt($ligne['updated_at'])) {
            return;
        }

        // `updateOrCreate(['id' => ...], ...)` ne suffirait pas : `id` n'est
        // fillable sur aucun modèle du registre, la création silencieusement
        // ignorerait la clé et attribuerait un autre identifiant local,
        // brisant la correspondance avec le serveur distant. L'affectation
        // directe (`->id = `) contourne le mass-assignment pour cette seule
        // colonne, sans toucher au reste des règles `fillable` du modèle.
        $instance = $existante ?? new $modele();
        $instance->id = $ligne['id'];
        // `updated_at` a servi à l'arbitrage ci-dessus ; Eloquent le régénère
        // de toute façon à l'enregistrement — inutile et pas nécessairement
        // `fillable` de le repasser en attribut.
        $instance->fill(collect($ligne)->except(['id', 'updated_at'])->all());
        $instance->save();
    }
}
