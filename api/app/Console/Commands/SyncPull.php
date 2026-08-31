<?php

namespace App\Console\Commands;

use App\Models\DesktopProvisioning;
use App\Models\DesktopProvisioningEcole;
use App\Models\SyncTombstone;
use App\Support\Sync\RegistreSync;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tire les changements du serveur distant vers la base locale (client
 * desktop offline). Réutilise tel quel le protocole déjà en place pour le
 * mobile ({@see \App\Http\Controllers\Api\V1\SyncController::pull()}) :
 * cette instance locale se comporte ici comme n'importe quel client sync.
 *
 * Un appel par école ({@see DesktopProvisioningEcole}), avec son propre
 * curseur : `SyncController::pull()` ne résout jamais qu'une seule école à
 * la fois (`X-School-Id`, ou l'école par défaut du compte à défaut d'en-tête)
 * — un compte non borné à une seule école (super admin d'un complexe) n'a
 * donc pas d'appel agrégé possible, il faut boucler.
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

        $ecoles = $provisioning->ecoles;

        if ($ecoles->isEmpty()) {
            $this->error('Aucune école associée à ce poste : rien à synchroniser.');

            return self::FAILURE;
        }

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

        $echec = false;

        try {
            foreach ($ecoles as $ecoleProvisioning) {
                try {
                    if (! $this->tirerEcole($provisioning, $ecoleProvisioning)) {
                        $echec = true;
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    // Un aléa réseau (coupure, DNS, timeout) sur UNE école ne
                    // doit pas priver les écoles suivantes de la boucle de
                    // leur propre tentative — observé en conditions réelles :
                    // un timeout sur la 2e école d'un compte en écoutant 3
                    // laissait la 3e totalement non synchronisée, sans que
                    // rien ne le signale au-delà d'un curseur resté `null`.
                    Log::warning('sync:pull erreur réseau', [
                        'school_id' => $ecoleProvisioning->school_id,
                        'erreur' => $e->getMessage(),
                    ]);
                    $this->error("École #{$ecoleProvisioning->school_id} : erreur réseau, réessaiera au prochain sync.");
                    $echec = true;
                }
            }
        } finally {
            // Toujours réactivée, même sur un échec ou une exception : le
            // reste de l'application (écritures normales de l'utilisateur)
            // doit retrouver l'intégrité référentielle habituelle dès que ce
            // lot est traité.
            DB::statement('PRAGMA foreign_keys = ON');
        }

        return $echec ? self::FAILURE : self::SUCCESS;
    }

    /** Pull complet d'une seule école, jusqu'à épuisement de ses pages. */
    private function tirerEcole(DesktopProvisioning $provisioning, DesktopProvisioningEcole $ecoleProvisioning): bool
    {
        $entitesParCle = RegistreSync::entites();
        $complet = false;
        $curseur = $ecoleProvisioning->curseur_sync;
        $totalLignes = 0;
        $totalSuppressions = 0;

        while (! $complet) {
            $reponse = Http::withToken($provisioning->token)
                ->withHeaders(['X-School-Id' => $ecoleProvisioning->school_id])
                ->baseUrl(rtrim($provisioning->serveur_url, '/').'/api/v1')
                ->acceptJson()
                ->get('sync', array_filter(['depuis' => $curseur]));

            if ($reponse->failed()) {
                Log::warning('sync:pull échec HTTP', ['school_id' => $ecoleProvisioning->school_id, 'statut' => $reponse->status()]);
                $this->error("École #{$ecoleProvisioning->school_id} : le serveur distant a répondu {$reponse->status()}.");

                return false;
            }

            $payload = $reponse->json('data') ?? [];

            foreach ((array) ($payload['donnees'] ?? []) as $cle => $lignes) {
                if (! isset($entitesParCle[$cle])) {
                    continue;
                }

                $modele = $entitesParCle[$cle]['modele'];

                foreach ($lignes as $ligne) {
                    // Une ligne isolée qui viole une contrainte (ex. deux
                    // comptes comptables distincts partageant le même code,
                    // une incohérence déjà présente côté serveur) ne doit pas
                    // priver l'utilisateur de tout le reste du lot — des
                    // milliers de lignes saines à côté d'une poignée déjà en
                    // défaut ailleurs.
                    try {
                        $this->appliquerLigne($modele, $ligne);
                        $totalLignes++;
                    } catch (QueryException $e) {
                        Log::warning('sync:pull ligne ignorée', [
                            'school_id' => $ecoleProvisioning->school_id,
                            'entite' => $cle,
                            'id' => $ligne['id'] ?? null,
                            'erreur' => $e->getMessage(),
                        ]);
                    }
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

        $ecoleProvisioning->update(['curseur_sync' => $curseur, 'dernier_pull_le' => now()]);

        $this->info("École #{$ecoleProvisioning->school_id} : {$totalLignes} ligne(s), {$totalSuppressions} suppression(s).");

        return true;
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
        // colonne.
        $instance = $existante ?? new $modele();
        $instance->id = $ligne['id'];
        // `updated_at` a servi à l'arbitrage ci-dessus ; Eloquent le régénère
        // de toute façon à l'enregistrement — inutile et pas nécessairement
        // `fillable` de le repasser en attribut.
        $instance->fill(collect($ligne)->except(['id', 'updated_at'])->all());
        $instance->save();
    }
}
