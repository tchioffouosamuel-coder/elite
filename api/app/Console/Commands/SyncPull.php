<?php

namespace App\Console\Commands;

use App\Models\DesktopProvisioning;
use App\Models\DesktopProvisioningEcole;
use App\Models\SyncFichierEnAttente;
use App\Observers\ContactsTuteurObserver;
use App\Support\Sync\RafraichitJetonDesktop;
use App\Support\Sync\RegistreSync;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tire les changements du serveur distant vers la base locale (client
 * desktop offline). Réutilise tel quel le protocole déjà en place pour le
 * mobile ({@see \App\Http\Controllers\Api\V1\SyncController::pull()}) :
 * cette instance locale se comporte ici comme n'importe quel client sync.
 *
 * Un appel HTTP par (compte, école, LOT d'entités) — {@see DesktopProvisioningEcole},
 * {@see TAILLE_LOT} — plutôt qu'un unique appel par (compte, école) qui
 * recevrait le registre entier entremêlé sur les mêmes pages (ce que fait
 * déjà le mobile, `?entites=` omis) : demander le registre en petits lots
 * plutôt qu'en un seul bloc permet de savoir, à tout instant, QUEL groupe de
 * tables est en cours de téléchargement — indispensable à la modale de
 * premier clonage (cf. `--json`) — sans rien changer côté serveur distant, ni
 * retomber dans la lenteur d'un appel par entité individuelle (80 par école).
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
            $this->message('Aucune instance provisionnée : rien à synchroniser.', erreur: true);

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

        // Sur le serveur, modifier un tuteur ou son téléphone avance
        // `updated_at` de ses élèves pour qu'ils soient renvoyés aux clients.
        // Ici ces mêmes lignes ne font qu'ARRIVER : rejouer l'observateur
        // daterait chaque élève local de l'instant du pull, donc plus récent
        // que toute version distante à venir — que l'arbitrage « le plus
        // récent gagne » (cf. `appliquerLigne()`) refuserait ensuite.
        ContactsTuteurObserver::$suspendu = true;

        $echec = false;

        $ecolesTotal = $provisionings->sum(fn(DesktopProvisioning $p) => $p->ecoles->count());
        $lotsParEcole = (int) ceil(count(RegistreSync::cles()) / self::TAILLE_LOT);
        $this->emettre(['type' => 'debut', 'ecoles' => $ecolesTotal, 'entites_par_ecole' => $lotsParEcole]);

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
                        $this->message("Compte #{$provisioning->user_id}, école #{$ecoleProvisioning->school_id} : erreur réseau, réessaiera au prochain sync.", erreur: true);
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
                        $this->message("Compte #{$provisioning->user_id}, école #{$ecoleProvisioning->school_id} : le serveur distant a refusé la requête ({$e->response?->status()}).", erreur: true);
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
            ContactsTuteurObserver::$suspendu = false;
        }

        $this->emettre(['type' => 'fin', 'echec' => $echec]);

        return $echec ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Nombre d'entités regroupées dans un même appel HTTP au serveur distant
     * (cf. `tirerLot()`). Le serveur sait déjà tout renvoyer en un seul appel
     * — c'est ce que fait le mobile — mais y aller entité par entité (80 par
     * école) payait un aller-retour réseau complet par TABLE, y compris pour
     * les dizaines qui n'ont jamais rien de nouveau à envoyer : c'était, de
     * loin, le principal poste de lenteur du premier clonage, largement
     * devant le volume de données lui-même. Un compromis plutôt que « tout
     * en un » : ça garde une progression par lot visible dans la modale
     * (`PremiereSynchronisationModal`), tout en divisant par ~10 le nombre
     * d'allers-retours.
     */
    private const TAILLE_LOT = 10;

    /**
     * Lignes demandées par entité et par page (`?limite=`, cf.
     * `SyncController::pull()`). Le plafond par défaut du serveur (500) est
     * taillé pour un téléphone ; un poste desktop absorbe sans peine des pages
     * quatre fois plus grosses, et un premier clonage de plusieurs centaines
     * de milliers de lignes fait d'autant moins d'allers-retours. Un serveur
     * plus ancien ignore ce paramètre et garde ses pages de 500.
     */
    private const LIGNES_PAR_PAGE = 2000;

    /** Repli quand une grosse page échoue (mémoire du serveur, liaison trop lente) : le plafond historique. */
    private const LIGNES_PAR_PAGE_REPLI = 500;

    /**
     * Lignes appliquées par transaction locale. Assez pour ne plus payer un
     * commit par ligne, assez peu pour ne jamais tenir le verrou d'écriture
     * SQLite plus d'une fraction de seconde face aux écritures de l'écran.
     */
    private const LIGNES_PAR_TRANSACTION = 500;

    private int $lignesParPage = self::LIGNES_PAR_PAGE;

    /**
     * Pull complet d'une seule école.
     *
     * Premier clonage (aucun curseur encore) : le registre découpé en lots de
     * {@see TAILLE_LOT} entités, chaque lot demandé en un seul appel HTTP par
     * page (cf. `tirerLot()`). Chaque lot avance avec SON curseur, conservé
     * dans `progression_clonage` : un clonage interrompu reprend chaque lot
     * là où il en était. Le curseur de l'école, lui, n'est posé qu'une fois
     * tous les lots terminés — l'avancer en cours de route faisait repartir
     * les lots pas encore commencés du curseur d'un autre, et ils ne
     * recevaient jamais leurs lignes plus anciennes.
     *
     * Rattrapage (curseur déjà posé) : tout le registre en un seul appel,
     * comme le fait le mobile. Découper en lots n'y sert à rien — personne ne
     * regarde de progression — et coûtait un aller-retour par lot, à chaque
     * cycle, pour apprendre presque toujours qu'il n'y a rien de nouveau.
     */
    private function tirerEcole(DesktopProvisioning $provisioning, DesktopProvisioningEcole $ecoleProvisioning): bool
    {
        $curseurDepart = $ecoleProvisioning->curseur_sync;
        $premierClonage = $curseurDepart === null;
        $lots = $premierClonage
            ? array_chunk(RegistreSync::cles(), self::TAILLE_LOT)
            : [RegistreSync::cles()];
        $progression = $premierClonage ? (array) $ecoleProvisioning->progression_clonage : [];
        $curseursObtenus = [];
        $totalLignes = 0;
        $totalSuppressions = 0;

        foreach ($lots as $index => $lot) {
            $this->emettre([
                'type' => 'entite_debut',
                'school_id' => $ecoleProvisioning->school_id,
                'cle' => $lot[0],
                'cles' => $lot,
                'etape' => $index + 1,
                'total_etapes' => count($lots),
            ]);

            // Signature du contenu du lot, pas son rang : si le registre
            // change entre deux versions de l'application, un lot recomposé
            // ne reprend pas la progression d'un autre.
            $cleLot = md5(implode(',', $lot));
            $etatLot = $progression[$cleLot] ?? [];

            if ($premierClonage && ($etatLot['termine'] ?? false)) {
                $curseursObtenus[] = $etatLot['curseur'];
                $this->emettre([
                    'type' => 'entite_fin',
                    'school_id' => $ecoleProvisioning->school_id,
                    'cle' => $lot[0],
                    'cles' => $lot,
                    'etape' => $index + 1,
                    'total_etapes' => count($lots),
                    'lignes' => 0,
                ]);

                continue;
            }

            [$lignes, $suppressions, $curseur] = $this->tirerLot(
                $provisioning,
                $ecoleProvisioning,
                $lot,
                $premierClonage ? ($etatLot['curseur'] ?? null) : $curseurDepart,
                $premierClonage ? $cleLot : null,
            );

            $totalLignes += $lignes;
            $totalSuppressions += $suppressions;
            if ($curseur !== null) {
                $curseursObtenus[] = $curseur;
            }

            $this->emettre([
                'type' => 'entite_fin',
                'school_id' => $ecoleProvisioning->school_id,
                'cle' => $lot[0],
                'cles' => $lot,
                'etape' => $index + 1,
                'total_etapes' => count($lots),
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
            'progression_clonage' => null,
            'dernier_pull_le' => now(),
        ]);

        $this->message("École #{$ecoleProvisioning->school_id} : {$totalLignes} ligne(s), {$totalSuppressions} suppression(s).");

        return true;
    }

    /**
     * Pull complet d'un lot de plusieurs entités pour une école, en un seul
     * appel HTTP par page plutôt qu'un appel par entité : le serveur distant
     * ({@see \App\Http\Controllers\Api\V1\SyncController::pull()}) accepte
     * déjà `entites=cle1,cle2,...` et applique un unique curseur/`complet` à
     * TOUT le lot demandé — cette méthode applique donc, à l'échelle d'un lot
     * de {@see TAILLE_LOT} entités, la même logique de pagination qu'un
     * unique appel entité par entité aurait suivie pour chacune.
     *
     * Rappeler la MÊME liste d'entités à chaque page (pas seulement celles
     * encore incomplètes) est volontaire et sans risque : une entité déjà
     * drainée ne renverra simplement plus rien pour le curseur avancé, au
     * prix d'un champ vide dans la réponse — bien moins coûteux que de
     * complexifier le lot demandé à chaque itération.
     *
     * @param  ?string  $cleProgression  Signature du lot pendant un premier
     *                                   clonage (sa progression est alors
     *                                   tenue à part, cf. `tirerEcole()`) ;
     *                                   `null` en rattrapage, où le curseur
     *                                   de la page est celui de l'école.
     * @return array{0: int, 1: int, 2: ?string} Lignes appliquées,
     *                                            suppressions rejouées, et le
     *                                            dernier curseur renvoyé par
     *                                            le serveur distant pour ce
     *                                            lot (`null` si aucun appel
     *                                            n'a abouti).
     */
    private function tirerLot(DesktopProvisioning $provisioning, DesktopProvisioningEcole $ecoleProvisioning, array $lot, ?string $curseurDepart, ?string $cleProgression): array
    {
        $registre = RegistreSync::entites();
        $definitions = collect($lot)->mapWithKeys(fn(string $cle) => [$cle => $registre[$cle]]);
        $complet = false;
        $curseur = $curseurDepart;
        $dernierCurseurRecu = null;
        $totalLignes = 0;
        $totalSuppressions = 0;
        $erreursApplication = [];

        while (! $complet) {
            $payload = $this->executerRequete($provisioning, $ecoleProvisioning->school_id, $lot, $curseur);

            foreach ($definitions as $cle => $definition) {
                // Par blocs, chacun dans sa transaction : un commit par ligne
                // plafonnait l'application à une centaine de lignes par
                // seconde, de loin le premier poste de lenteur d'un clonage.
                foreach (array_chunk((array) ($payload['donnees'][$cle] ?? []), self::LIGNES_PAR_TRANSACTION) as $bloc) {
                    $totalLignes += DB::transaction(
                        fn() => $this->appliquerBloc($provisioning, $ecoleProvisioning, $cle, $definition, $bloc, $erreursApplication),
                    );
                }
            }

            if ($erreursApplication !== []) {
                throw new \RuntimeException(
                    'Synchronisation interrompue : ' . count($erreursApplication) .
                        ' ligne(s) n’ont pas pu être appliquées (' .
                        implode(', ', array_slice($erreursApplication, 0, 5)) .
                        '). Les données déjà reçues sont conservées; corrigez la base locale puis réessayez.',
                );
            }

            foreach ((array) ($payload['suppressions'] ?? []) as $suppression) {
                if (in_array($suppression['entite'] ?? null, $lot, true)) {
                    $definitions[$suppression['entite']]['modele']::query()->whereKey($suppression['id'])->delete();
                    $totalSuppressions++;
                }
            }

            $curseur = $payload['curseur'] ?? $curseur;
            $dernierCurseurRecu = $curseur;
            $complet = (bool) ($payload['complet'] ?? true);

            // Persisté après CHAQUE page, pas seulement à la fin du lot : un
            // établissement volumineux peut demander des dizaines de pages,
            // donc autant d'allers-retours réseau successifs — un aléa isolé
            // sur l'un d'eux ne doit pas effacer la progression déjà
            // appliquée en local et forcer à tout retélécharger depuis le
            // début au prochain essai. Observé en conditions réelles : un
            // timeout au bout d'1h30 de pagination faisait systématiquement
            // repartir de zéro l'école la plus volumineuse.
            if ($cleProgression === null) {
                $ecoleProvisioning->update(['curseur_sync' => $curseur]);
            } else {
                $ecoleProvisioning->update(['progression_clonage' => [
                    ...(array) $ecoleProvisioning->progression_clonage,
                    $cleProgression => ['curseur' => $curseur, 'termine' => $complet],
                ]]);
            }

            if (! $complet) {
                $this->emettre([
                    'type' => 'entite_progres',
                    'school_id' => $ecoleProvisioning->school_id,
                    'cle' => $lot[0],
                    'lignes' => $totalLignes,
                ]);
            }
        }

        return [$totalLignes, $totalSuppressions, $dernierCurseurRecu];
    }

    /**
     * Applique un bloc de lignes d'une même entité — à appeler dans une
     * transaction. Les versions locales sont lues en une seule requête pour
     * tout le bloc plutôt qu'une par ligne.
     *
     * @param  list<array<string, mixed>>  $bloc
     * @param  list<string>  $erreursApplication  Complété par les lignes rejetées par la base.
     * @return int Lignes traitées sans erreur.
     */
    private function appliquerBloc(DesktopProvisioning $provisioning, DesktopProvisioningEcole $ecoleProvisioning, string $cle, array $definition, array $bloc, array &$erreursApplication): int
    {
        $existantes = $definition['modele']::query()
            ->findMany(array_filter(array_column($bloc, 'id')))
            ->getDictionary();
        $fichiers = [];
        $traitees = 0;

        foreach ($bloc as $ligne) {
            // Une ligne isolée qui viole une contrainte (ex. deux comptes
            // comptables distincts partageant le même code, une incohérence
            // déjà présente côté serveur) ne doit pas priver l'utilisateur de
            // tout le reste du lot — des milliers de lignes saines à côté
            // d'une poignée déjà en défaut ailleurs. SQLite n'annule que
            // l'instruction fautive : la transaction du bloc, elle, continue.
            try {
                $instance = $this->appliquerLigne($definition, $ligne, $existantes[$ligne['id'] ?? null] ?? null);

                if ($instance !== null) {
                    // Une ligne répétée dans le même bloc doit retrouver
                    // celle qu'on vient d'écrire, pas tenter de la recréer.
                    $existantes[$instance->getKey()] = $instance;
                    array_push($fichiers, ...$this->fichiersReferences($provisioning, $ligne));
                }
                $traitees++;
            } catch (QueryException $e) {
                Log::warning('sync:pull ligne ignorée', [
                    'school_id' => $ecoleProvisioning->school_id,
                    'entite' => $cle,
                    'id' => $ligne['id'] ?? null,
                    'erreur' => $e->getMessage(),
                ]);
                $erreursApplication[] = $cle . '#' . ($ligne['id'] ?? '?');
            }
        }

        // `upsert` plutôt qu'une création simple : une même ligne peut
        // retraverser la synchro plusieurs fois avant que sa photo n'ait été
        // effectivement téléchargée (page suivante, cycle périodique suivant)
        // sans que ce soit une erreur — `chemin` est unique, on se contente
        // de rafraîchir `serveur_url` au cas où ce chemin ait changé de
        // compte entre deux passages (multi-comptes sur le même poste).
        if ($fichiers !== []) {
            SyncFichierEnAttente::query()->upsert(array_values(array_column($fichiers, null, 'chemin')), ['chemin'], ['serveur_url']);
        }

        return $traitees;
    }

    /**
     * Une page pour un lot d'entités, avec rafraîchissement du jeton d'accès
     * sur un 401 (une seule tentative, pour ne jamais boucler si le
     * rafraîchissement lui-même est refusé).
     *
     * Une grosse page ({@see LIGNES_PAR_PAGE}) qui échoue côté serveur ou
     * réseau est redemandée une fois à la taille historique, que l'on garde
     * ensuite jusqu'à la fin de la commande : mieux vaut un clonage plus lent
     * qu'un clonage qui bute indéfiniment sur la même page.
     *
     * @param  list<string>  $cles
     * @return array{donnees?: array, suppressions?: array, curseur?: string, complet?: bool}
     */
    private function executerRequete(DesktopProvisioning $provisioning, int $schoolId, array $cles, ?string $depuis, bool $jetonDejaRafraichi = false): array
    {
        try {
            return $this->demanderPage($provisioning, $schoolId, $cles, $depuis, $jetonDejaRafraichi);
        } catch (ConnectionException|RequestException $e) {
            $erreurServeur = $e instanceof ConnectionException || ($e->response?->status() ?? 0) >= 500;

            if (! $erreurServeur || $this->lignesParPage <= self::LIGNES_PAR_PAGE_REPLI) {
                throw $e;
            }

            Log::warning('sync:pull page réduite', ['school_id' => $schoolId, 'erreur' => $e->getMessage()]);
            $this->lignesParPage = self::LIGNES_PAR_PAGE_REPLI;

            return $this->demanderPage($provisioning, $schoolId, $cles, $depuis, $jetonDejaRafraichi);
        }
    }

    /**
     * @param  list<string>  $cles
     * @return array{donnees?: array, suppressions?: array, curseur?: string, complet?: bool}
     */
    private function demanderPage(DesktopProvisioning $provisioning, int $schoolId, array $cles, ?string $depuis, bool $jetonDejaRafraichi): array
    {
        try {
            $reponse = Http::withToken($provisioning->token)
                ->withHeaders(['X-School-Id' => $schoolId])
                ->baseUrl(rtrim($provisioning->serveur_url, '/') . '/api/v1')
                ->acceptJson()
                // Le timeout par défaut du client HTTP (30s, cf. Laravel) est
                // parfois trop court pour une page pleine :
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
                ->get('sync', array_filter([
                    'depuis' => $depuis,
                    'entites' => implode(',', $cles),
                    'limite' => $this->lignesParPage,
                ]));
        } catch (RequestException $e) {
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
                return $this->demanderPage($provisioning, $schoolId, $cles, $depuis, jetonDejaRafraichi: true);
            }

            throw $e;
        }

        return (array) ($reponse->json('data') ?? []);
    }

    /**
     * Upsert d'une ligne reçue, avec la règle du plus récent qui gagne.
     *
     * @param  ?Model  $existante  Version locale de la ligne, lue pour tout
     *                             le bloc par `appliquerBloc()`.
     * @return ?Model La ligne appliquée (créée ou mise à jour) — `null` si
     *                elle a été ignorée (conflit : la version locale est plus
     *                récente, pas encore poussée).
     */
    private function appliquerLigne(array $definition, array $ligne, ?Model $existante): ?Model
    {
        if (! isset($ligne['id'])) {
            return null;
        }

        $modele = $definition['modele'];

        // La ligne distante ne porte pas forcément `updated_at` (colonnes
        // projetées par `RegistreSync`) : sans base de comparaison, on
        // applique — c'est le cas d'une création locale jamais vue avant.
        if (
            $existante !== null && isset($ligne['updated_at'], $existante->updated_at)
            && $existante->updated_at->gt($ligne['updated_at'])
        ) {
            return null;
        }

        // `updateOrCreate(['id' => ...], ...)` ne suffirait pas : `id` n'est
        // fillable sur aucun modèle du registre, la création silencieusement
        // ignorerait la clé et attribuerait un autre identifiant local,
        // brisant la correspondance avec le serveur distant. L'affectation
        // directe (`->id = `) contourne le mass-assignment pour cette seule
        // colonne.
        $instance = $existante ?? new $modele();
        $instance->id = $ligne['id'];
        // Seules les colonnes déclarées : les `extras` d'une entité (rôles
        // et écoles d'un compte, cf. RegistreSync) ne sont pas des colonnes
        // de sa table et sont appliqués après la sauvegarde.
        $instance->fill(array_diff_key(
            array_intersect_key($ligne, array_flip($definition['colonnes'])),
            ['id' => true, 'updated_at' => true],
        ));

        // `$instance->timestamps = false` : sans ça, Eloquent réécrit
        // `updated_at` à l'heure de CETTE sauvegarde locale à chaque appel —
        // y compris pour une ligne qui n'a fait que traverser la synchro sans
        // la moindre modification réelle. Le prochain arbitrage ci-dessus
        // (ligne 373) comparerait alors « il y a quelques secondes » (l'heure
        // de sauvegarde locale) à l'`updated_at` réel, potentiellement bien
        // plus ancien, du serveur distant — et gagnerait à tort, bloquant
        // pour toujours toute mise à jour future de cette ligne, y compris
        // celle d'une colonne ajoutée après coup au registre et retéléchargée
        // via un curseur remis à zéro. Observé en conditions réelles :
        // `preinscriptions.annee_scolaire_id`, ajoutée au registre après que
        // des milliers de lignes avaient déjà été synchronisées une première
        // fois, restait `null` indéfiniment malgré plusieurs reclonages
        // complets — le serveur distant renvoyait pourtant bien la valeur à
        // chaque appel, seule son application était silencieusement rejetée
        // ligne par ligne. On préserve donc le VRAI `updated_at` distant,
        // pour que l'arbitrage compare toujours deux dates de modification
        // réelles, jamais une date de sauvegarde locale.
        $instance->timestamps = false;
        if (isset($ligne['updated_at'])) {
            $instance->updated_at = $ligne['updated_at'];
        }
        // `timestamps = false` désactive aussi la gestion automatique de
        // `created_at` pour une création : sans ce repli, une ligne jamais
        // vue localement se serait retrouvée avec `created_at` à `null` —
        // fatal pour le moindre code qui l'utilise sans vérification (ex.
        // `PreinscriptionService::anciensEleves()`, `$eleve->created_at->lessThan(...)`).
        // Le registre ne projette de toute façon jamais le vrai `created_at`
        // distant (cf. `RegistreSync`) : la valeur de `updated_at` de cette
        // même ligne reste le repli le plus proche de la réalité.
        if ($existante === null) {
            $instance->created_at = $instance->updated_at ?? now();
        }
        if (isset($definition['avant_sauvegarde'])) {
            ($definition['avant_sauvegarde'])($instance, $existante === null);
        }
        $instance->save();

        if (isset($definition['apres_sauvegarde'])) {
            ($definition['apres_sauvegarde'])($instance, $ligne);
        }

        return $instance;
    }

    /**
     * Fichiers à mettre en file pour une ligne tout juste appliquée : chacun
     * de ceux que référence une colonne `*_path` (photo d'élève ou de membre
     * du personnel, justificatif de dépense…) — le téléchargement effectif
     * est délégué à {@see \App\Console\Commands\SyncFichiers}, en tâche de
     * fond, pour ne plus jamais retarder la fin de `sync:pull` lui-même : un
     * premier clonage sur un grand établissement (plusieurs milliers de
     * photos, une requête HTTP synchrone par fichier) pouvait auparavant
     * prendre plusieurs minutes de plus rien que pour ça — observé en
     * conditions réelles, alors que ces fichiers n'affectent jamais la
     * validité des données déjà appliquées.
     *
     * @return list<array{chemin: string, serveur_url: string, created_at: \Illuminate\Support\Carbon}>
     */
    private function fichiersReferences(DesktopProvisioning $provisioning, array $ligne): array
    {
        $chemins = [];

        foreach ($ligne as $colonne => $valeur) {
            if (! str_ends_with($colonne, '_path') || ! is_string($valeur) || $valeur === '' || str_contains($valeur, '..')) {
                continue;
            }

            $chemins[] = [
                'chemin' => $valeur,
                'serveur_url' => $provisioning->serveur_url,
                'created_at' => now(),
            ];
        }

        return $chemins;
    }

    /**
     * Message lisible pour un humain. En `--json`, stdout est réservé au flux
     * d'évènements (cf. `emettre()`) : le texte passe alors sur stderr, que
     * `main.cjs` journalise déjà, plutôt que de s'intercaler entre deux
     * lignes JSON.
     */
    private function message(string $texte, bool $erreur = false): void
    {
        if ($this->json) {
            if (defined('STDERR')) {
                fwrite(STDERR, $texte . PHP_EOL);
            }

            return;
        }

        $erreur ? $this->error($texte) : $this->info($texte);
    }

    private function emettre(array $evenement): void
    {
        if (! $this->json) {
            return;
        }

        $this->output->writeln(json_encode($evenement, JSON_UNESCAPED_UNICODE));
    }
}
