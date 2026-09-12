<?php

namespace App\Console\Commands;

use App\Models\DesktopProvisioning;
use App\Models\DesktopProvisioningEcole;
use App\Support\Sync\RafraichitJetonDesktop;
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
 * Un appel par (compte, école, entité) — {@see DesktopProvisioningEcole} —
 * plutôt qu'un unique appel par (compte, école) qui recevrait toutes les
 * entités entremêlées sur les mêmes pages : boucler entité par entité (le
 * serveur distant accepte déjà `?entites=` pour n'en réclamer qu'une, cf.
 * `SyncController::entitesDemandees()`) permet de savoir, à tout instant,
 * QUELLE table est en cours de téléchargement — indispensable à la modale de
 * premier clonage (cf. `--json`) — sans rien changer côté serveur distant, le
 * mobile continuant de tout demander en une fois.
 *
 * Résolution de conflit : le plus récent gagne. Une ligne locale plus
 * récente que la ligne distante reçue n'est PAS écrasée — elle n'a pas
 * encore été poussée (cf. {@see SyncPush}), l'écraser perdrait une
 * modification faite hors-ligne.
 */
class SyncPull extends Command
{
    use RafraichitJetonDesktop;

    protected $signature = 'sync:pull {--json : Émet une ligne JSON par évènement sur la sortie standard, pour une modale de progression}';

    protected $description = "Tire les données du serveur distant vers la base locale (client desktop)";

    private bool $json = false;

    public function handle(): int
    {
        $this->json = (bool) $this->option('json');

        $provisionings = DesktopProvisioning::all();

        if ($provisionings->isEmpty()) {
            $this->error('Aucune instance provisionnée : rien à synchroniser.');

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

        $ecolesTotal = $provisionings->sum(fn (DesktopProvisioning $p) => $p->ecoles->count());
        $this->emettre(['type' => 'debut', 'ecoles' => $ecolesTotal, 'entites_par_ecole' => count(RegistreSync::cles())]);

        try {
            $ecoleIndex = 0;

            foreach ($provisionings as $provisioning) {
                // Porte `DesktopProvisioningController::connexion()` :
                // l'accès local à l'application reste bloqué tant que ce
                // compte n'a pas, au moins une fois, répliqué la TOTALITÉ de
                // ses écoles sans le moindre échec — une seule école en
                // erreur suffit à ne pas armer le drapeau à ce passage,
                // même si les autres ont parfaitement réussi.
                $echecProvisioning = false;

                foreach ($provisioning->ecoles as $ecoleProvisioning) {
                    $ecoleIndex++;
                    $this->emettre([
                        'type' => 'ecole_debut',
                        'school_id' => $ecoleProvisioning->school_id,
                        'nom' => $ecoleProvisioning->school?->name,
                        'index' => $ecoleIndex,
                        'total' => $ecolesTotal,
                    ]);

                    try {
                        if (! $this->tirerEcole($provisioning, $ecoleProvisioning)) {
                            $echec = true;
                            $echecProvisioning = true;
                        }
                        $this->emettre(['type' => 'ecole_fin', 'school_id' => $ecoleProvisioning->school_id, 'index' => $ecoleIndex, 'total' => $ecolesTotal]);
                    } catch (\Illuminate\Http\Client\ConnectionException $e) {
                        // Un aléa réseau (coupure, DNS, timeout) sur UNE école ne
                        // doit pas priver les écoles suivantes de la boucle de
                        // leur propre tentative — observé en conditions réelles :
                        // un timeout sur la 2e école d'un compte en écoutant 3
                        // laissait la 3e totalement non synchronisée, sans que
                        // rien ne le signale au-delà d'un curseur resté `null`.
                        Log::warning('sync:pull erreur réseau', [
                            'user_id' => $provisioning->user_id,
                            'school_id' => $ecoleProvisioning->school_id,
                            'erreur' => $e->getMessage(),
                        ]);
                        $this->error("Compte #{$provisioning->user_id}, école #{$ecoleProvisioning->school_id} : erreur réseau, réessaiera au prochain sync.");
                        $this->emettre(['type' => 'ecole_erreur', 'school_id' => $ecoleProvisioning->school_id, 'message' => 'Erreur réseau, nouvelle tentative au prochain cycle.']);
                        $echec = true;
                        $echecProvisioning = true;
                    } catch (\Illuminate\Http\Client\RequestException $e) {
                        // `retry()` (cf. `executerRequete()`) relance cette
                        // exception, par défaut, après épuisement de ses
                        // tentatives sur toute réponse en échec (jeton d'accès
                        // expiré — TTL de 24h, cf.
                        // `AuthService::ACCESS_TOKEN_TTL_MINUTES` — rejeté même
                        // après rafraîchissement, compte désactivé côté serveur
                        // distant...). Sans ce filet, elle remontait telle
                        // quelle hors de la boucle et interrompait `sync:pull`
                        // en plein milieu : tout compte provisionné APRÈS celui
                        // en défaut sur ce même poste perdait alors sa propre
                        // chance de se synchroniser à ce passage — observé en
                        // conditions réelles avec un jeton expiré sur le
                        // premier compte provisionné, qui privait
                        // silencieusement les autres.
                        Log::warning('sync:pull requête refusée', [
                            'user_id' => $provisioning->user_id,
                            'school_id' => $ecoleProvisioning->school_id,
                            'statut' => $e->response?->status(),
                        ]);
                        $this->error("Compte #{$provisioning->user_id}, école #{$ecoleProvisioning->school_id} : le serveur distant a refusé la requête ({$e->response?->status()}).");
                        $this->emettre(['type' => 'ecole_erreur', 'school_id' => $ecoleProvisioning->school_id, 'message' => 'Le serveur distant a refusé la requête.']);
                        $echec = true;
                        $echecProvisioning = true;
                    }
                }

                if (! $echecProvisioning && ! $provisioning->clonage_initial_complet) {
                    $provisioning->update(['clonage_initial_complet' => true]);
                    $this->emettre(['type' => 'clonage_initial_complet', 'user_id' => $provisioning->user_id]);
                }
            }
        } finally {
            // Toujours réactivée, même sur un échec ou une exception : le
            // reste de l'application (écritures normales de l'utilisateur)
            // doit retrouver l'intégrité référentielle habituelle dès que ce
            // lot est traité.
            DB::statement('PRAGMA foreign_keys = ON');
        }

        $this->emettre(['type' => 'fin', 'echec' => $echec]);

        return $echec ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Pull complet d'une seule école : chaque entité du registre, l'une
     * après l'autre, jusqu'à épuisement de ses propres pages.
     */
    private function tirerEcole(DesktopProvisioning $provisioning, DesktopProvisioningEcole $ecoleProvisioning): bool
    {
        $cles = RegistreSync::cles();
        $curseurDepart = $ecoleProvisioning->curseur_sync;
        $curseursObtenus = [];
        $totalLignes = 0;
        $totalSuppressions = 0;

        foreach ($cles as $index => $cle) {
            $this->emettre([
                'type' => 'entite_debut',
                'school_id' => $ecoleProvisioning->school_id,
                'cle' => $cle,
                'etape' => $index + 1,
                'total_etapes' => count($cles),
            ]);

            [$lignes, $suppressions, $curseur] = $this->tirerEntite($provisioning, $ecoleProvisioning, $cle, $curseurDepart);

            $totalLignes += $lignes;
            $totalSuppressions += $suppressions;
            if ($curseur !== null) {
                $curseursObtenus[] = $curseur;
            }

            $this->emettre([
                'type' => 'entite_fin',
                'school_id' => $ecoleProvisioning->school_id,
                'cle' => $cle,
                'etape' => $index + 1,
                'total_etapes' => count($cles),
                'lignes' => $lignes,
            ]);
        }

        // Le plus ancien curseur obtenu parmi toutes les entités : chacune a
        // été interrogée à un instant légèrement différent (autant d'appels
        // HTTP séparés), retenir le plus récent ferait passer à la trappe
        // une écriture survenue côté serveur entre deux entités. Une entité
        // sans aucune ligne ni suppression renvoie `now()` au moment de son
        // propre appel : l'inclure reste sûr, juste conservateur.
        $ecoleProvisioning->update([
            'curseur_sync' => $curseursObtenus !== [] ? min($curseursObtenus) : $curseurDepart,
            'dernier_pull_le' => now(),
        ]);

        $this->info("École #{$ecoleProvisioning->school_id} : {$totalLignes} ligne(s), {$totalSuppressions} suppression(s).");

        return true;
    }

    /**
     * Pull complet d'une seule entité pour une école, jusqu'à épuisement de
     * ses pages.
     *
     * @return array{0: int, 1: int, 2: ?string} Lignes appliquées,
     *                                            suppressions rejouées, et le
     *                                            dernier curseur renvoyé par
     *                                            le serveur distant pour
     *                                            cette entité (`null` si
     *                                            aucun appel n'a abouti).
     */
    private function tirerEntite(DesktopProvisioning $provisioning, DesktopProvisioningEcole $ecoleProvisioning, string $cle, ?string $curseurDepart): array
    {
        $modele = RegistreSync::entites()[$cle]['modele'];
        $complet = false;
        $curseur = $curseurDepart;
        $dernierCurseurRecu = null;
        $totalLignes = 0;
        $totalSuppressions = 0;

        while (! $complet) {
            $payload = $this->executerRequete($provisioning, $ecoleProvisioning->school_id, $cle, $curseur);

            foreach ((array) ($payload['donnees'][$cle] ?? []) as $ligne) {
                // Une ligne isolée qui viole une contrainte (ex. deux
                // comptes comptables distincts partageant le même code,
                // une incohérence déjà présente côté serveur) ne doit pas
                // priver l'utilisateur de tout le reste du lot — des
                // milliers de lignes saines à côté d'une poignée déjà en
                // défaut ailleurs.
                try {
                    if ($this->appliquerLigne($modele, $ligne)) {
                        $this->telechargerFichiers($provisioning, $ligne);
                    }
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

            foreach ((array) ($payload['suppressions'] ?? []) as $suppression) {
                if (($suppression['entite'] ?? null) === $cle) {
                    $modele::query()->whereKey($suppression['id'])->delete();
                    $totalSuppressions++;
                }
            }

            $curseur = $payload['curseur'] ?? $curseur;
            $dernierCurseurRecu = $curseur;
            $complet = (bool) ($payload['complet'] ?? true);

            // Persisté après CHAQUE page, pas seulement à la fin de
            // l'entité : un établissement volumineux peut demander des
            // dizaines de pages (chacune plafonnée à 500 lignes), donc
            // autant d'allers-retours réseau successifs — un aléa isolé sur
            // l'un d'eux ne doit pas effacer la progression déjà appliquée
            // en local et forcer à tout retélécharger depuis le début au
            // prochain essai. Observé en conditions réelles : un timeout au
            // bout d'1h30 de pagination faisait systématiquement repartir de
            // zéro l'école la plus volumineuse.
            $ecoleProvisioning->update(['curseur_sync' => $curseur]);

            if (! $complet) {
                $this->emettre([
                    'type' => 'entite_progres',
                    'school_id' => $ecoleProvisioning->school_id,
                    'cle' => $cle,
                    'lignes' => $totalLignes,
                ]);
            }
        }

        return [$totalLignes, $totalSuppressions, $dernierCurseurRecu];
    }

    /**
     * Une page pour une entité donnée, avec rafraîchissement du jeton
     * d'accès sur un 401 (une seule tentative, pour ne jamais boucler si le
     * rafraîchissement lui-même est refusé).
     *
     * @return array{donnees?: array, suppressions?: array, curseur?: string, complet?: bool}
     */
    private function executerRequete(DesktopProvisioning $provisioning, int $schoolId, string $cle, ?string $depuis, bool $jetonDejaRafraichi = false): array
    {
        try {
            $reponse = Http::withToken($provisioning->token)
                ->withHeaders(['X-School-Id' => $schoolId])
                ->baseUrl(rtrim($provisioning->serveur_url, '/').'/api/v1')
                ->acceptJson()
                // Le timeout par défaut du client HTTP (30s, cf. Laravel) est
                // parfois trop court pour une page pleine (jusqu'à 500 lignes) :
                // observé en conditions réelles à 17s de réponse normale, et
                // jusqu'à un échec à 30s sous une latence réseau moins
                // favorable. `connectTimeout` séparé de `timeout` : un aléa sur
                // la connexion elle-même (DNS/TLS) ne doit pas se cacher
                // derrière un délai pensé pour la réponse.
                ->connectTimeout(30)
                ->timeout(180)
                // Une page qui échoue (réseau instable, coupure momentanée)
                // se retente seule, 3 fois avec un délai croissant, avant de
                // remonter l'échec au niveau de l'école : beaucoup moins
                // coûteux qu'un ré-essai de la commande entière, qui reprend
                // certes désormais à la bonne page (curseur persisté après
                // chaque page) mais reperd quand même la page en cours d'échec.
                // Par défaut, Laravel relance l'exception une fois les
                // tentatives épuisées (`retryThrow`) plutôt que de renvoyer
                // simplement la réponse en échec — c'est ce qui permet
                // d'intercepter un 401 ci-dessous pour rafraîchir le jeton
                // avant d'abandonner.
                ->retry(3, 3000)
                ->get('sync', array_filter(['depuis' => $depuis, 'entites' => $cle]));
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Le jeton d'accès n'est valable que 24h (cf.
            // `AuthService::ACCESS_TOKEN_TTL_MINUTES`) et rien ne le
            // renouvelait jamais ici avant ce correctif : un poste resté
            // ouvert, ou simplement pas relancé depuis la veille, voyait
            // alors TOUTE synchronisation échouer en silence dès le
            // lendemain de la connexion — observé en conditions réelles
            // (compte provisionné le 01/09, plus aucune donnée reçue
            // depuis). On tente donc un rafraîchissement via le jeton de
            // rafraîchissement (30 jours) avant d'abandonner.
            if ($e->response?->status() === 401 && ! $jetonDejaRafraichi && $this->rafraichirJeton($provisioning)) {
                return $this->executerRequete($provisioning, $schoolId, $cle, $depuis, jetonDejaRafraichi: true);
            }

            throw $e;
        }

        return (array) ($reponse->json('data') ?? []);
    }

    /**
     * Upsert d'une ligne reçue, avec la règle du plus récent qui gagne.
     *
     * @param  class-string  $modele
     * @return bool Vrai si la ligne a été appliquée (créée ou mise à jour) —
     *              faux si elle a été ignorée (conflit : la version locale
     *              est plus récente, pas encore poussée).
     */
    private function appliquerLigne(string $modele, array $ligne): bool
    {
        if (! isset($ligne['id'])) {
            return false;
        }

        $existante = $modele::query()->find($ligne['id']);

        // La ligne distante ne porte pas forcément `updated_at` (colonnes
        // projetées par `RegistreSync`) : sans base de comparaison, on
        // applique — c'est le cas d'une création locale jamais vue avant.
        if ($existante !== null && isset($ligne['updated_at'], $existante->updated_at)
            && $existante->updated_at->gt($ligne['updated_at'])) {
            return false;
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

        return true;
    }

    /**
     * Télécharge, pour une ligne tout juste appliquée, chaque fichier
     * référencé par une colonne `*_path` (photo d'élève ou de membre du
     * personnel, justificatif de dépense…).
     *
     * Aucun endpoint dédié : le disque `public` du serveur distant est déjà
     * servi tel quel (`{serveur}/storage/{chemin}`, la même URL que
     * `asset('storage/...')` construit côté API) — un simple GET suffit.
     * Retélécharge à chaque fois que la ligne change plutôt que de ne
     * combler que les fichiers manquants : un chemin peut rester identique
     * alors que son contenu a changé (remplacement d'une photo), et cette
     * méthode n'est appelée que pour des lignes réellement appliquées —
     * déjà rares en régime de croisière, une fois le pull initial passé.
     */
    private function telechargerFichiers(DesktopProvisioning $provisioning, array $ligne): void
    {
        foreach ($ligne as $colonne => $valeur) {
            if (! str_ends_with($colonne, '_path') || ! is_string($valeur) || $valeur === '' || str_contains($valeur, '..')) {
                continue;
            }

            $destination = storage_path('app/public/'.$valeur);

            try {
                $reponse = Http::baseUrl(rtrim($provisioning->serveur_url, '/'))
                    ->connectTimeout(10)
                    ->timeout(30)
                    ->get('storage/'.$valeur);

                if (! $reponse->successful()) {
                    Log::warning('sync:pull fichier introuvable', [
                        'chemin' => $valeur, 'statut' => $reponse->status(),
                    ]);

                    continue;
                }

                if (! is_dir(dirname($destination))) {
                    mkdir(dirname($destination), 0755, true);
                }

                file_put_contents($destination, $reponse->body());
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Même logique que l'aléa réseau plus haut : un fichier raté
                // ne doit pas interrompre le reste du lot, il manquera juste
                // à l'affichage jusqu'au prochain sync.
                Log::warning('sync:pull erreur réseau (fichier)', [
                    'chemin' => $valeur, 'erreur' => $e->getMessage(),
                ]);
            }
        }
    }

    private function emettre(array $evenement): void
    {
        if (! $this->json) {
            return;
        }

        $this->output->writeln(json_encode($evenement, JSON_UNESCAPED_UNICODE));
    }
}
