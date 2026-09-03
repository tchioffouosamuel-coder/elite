<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SyncTombstone;
use App\Support\Sync\RegistreSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Synchronisation delta pour l'application mobile.
 *
 * Un seul appel remplace le rejeu des 250 routes REST : le client envoie le
 * curseur de sa dernière synchronisation réussie, le serveur renvoie ce qui a
 * changé depuis, plus les suppressions, plus le nouveau curseur.
 */
class SyncController extends Controller
{
    /**
     * Plafond de lignes par entité et par appel. Une première synchronisation
     * sur un établissement de plusieurs milliers d'élèves doit se faire en
     * plusieurs passes plutôt qu'en une réponse que le téléphone n'arrivera
     * pas à désérialiser.
     */
    private const LOT_MAX = 500;

    /**
     * Plafond d'opérations poussées en une fois. Volontairement bas : chaque
     * opération rejoue un contrôleur complet, et un lot trop gros dépasserait
     * le temps d'exécution PHP avant d'avoir tout traité.
     */
    private const LOT_PUSH_MAX = 50;

    /**
     * Nombre de lignes par entité, dans le même périmètre (école + permissions
     * + classes du compte) que {@see pull()} — sert au poste desktop à
     * vérifier que sa réplique locale est bien complète (cf.
     * `DesktopProvisioningController::statutSync()`), plutôt que de faire
     * confiance silencieusement au fait qu'un `sync:pull` a tourné sans
     * erreur visible.
     */
    public function comptage(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $user = $request->user();
        $definitions = RegistreSync::entites($user);

        $comptages = [];

        foreach ($definitions as $cle => $definition) {
            if ($definition['permission'] !== null && ! $user->can($definition['permission'])) {
                continue;
            }

            $requete = $definition['modele']::query();
            ($definition['portee'])($requete, $schoolId);
            $comptages[$cle] = $requete->count();
        }

        return ApiResponse::success($comptages);
    }

    public function pull(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $user = $request->user();

        $depuis = $this->curseurDemande($request);
        $entitesDemandees = $this->entitesDemandees($request);

        /*
         * Le curseur est arrêté AVANT de lire quoi que ce soit. L'inverse
         * (prendre `now()` à la fin) perdrait toute ligne écrite pendant la
         * lecture : elle porterait un `updated_at` antérieur au curseur rendu,
         * et ne serait jamais renvoyée.
         */
        $curseur = now();

        $donnees = [];
        $bornes = [];

        // Calculé une fois pour tout l'appel : chaque `portee` du registre
        // compose déjà le filtre d'école avec le périmètre de $user (classes
        // enseignées/attribuées), sans quoi un compte borné (enseignant,
        // censeur, surveillant général) redescendrait l'école entière.
        $definitions = RegistreSync::entites($user);

        foreach ($entitesDemandees as $cle) {
            $definition = $definitions[$cle];

            // Le périmètre suit les privilèges : un enseignant qui n'a pas
            // `personnel.view` ne télécharge pas le fichier du personnel.
            if ($definition['permission'] !== null && ! $user->can($definition['permission'])) {
                continue;
            }

            [$lignes, $borne] = $this->lot($definition, $schoolId, $depuis);

            if ($borne !== null) {
                $bornes[] = $borne;
            }

            if ($lignes->isNotEmpty()) {
                // `updated_at` toujours inclus, même hors de `colonnes` : c'est
                // sur lui que le client desktop (SyncPull) arbitre un conflit
                // avec une ligne locale pas encore poussée (le plus récent
                // gagne). Le mobile, qui l'ignorait déjà, n'est pas affecté.
                $donnees[$cle] = $lignes->map->only([...$definition['colonnes'], 'updated_at'])->all();
            }
        }

        /*
         * Si un lot a été tronqué, le curseur rendu doit être celui de la
         * dernière ligne effectivement envoyée — et non `now()`, qui ferait
         * silencieusement disparaître tout le reliquat au prochain appel.
         * On retient la plus petite borne : les entités entièrement drainées
         * renverront quelques lignes en double au tour suivant, ce qu'un
         * upsert côté client absorbe sans effet.
         */
        // `min()` sur des `Carbon` compare nativement à la microseconde
        // (elles implémentent `DateTimeInterface`) — passer par
        // `getTimestamp()` (entier, secondes) puis `createFromTimestamp()`
        // reconstruisait une borne arrondie au début de la seconde, perdant
        // exactement la précision qui permet de départager des lignes
        // partageant la même seconde.
        $tronque = $bornes !== [];
        $curseurRendu = $tronque ? min($bornes) : $curseur;

        return ApiResponse::success([
            // Format Zulu (`...Z`) et non `+00:00` : le `+` d'un décalage se
            // décode en espace dans une chaîne de requête, rendant le curseur
            // illisible au retour et provoquant une resynchronisation complète
            // silencieuse à chaque appel. Précision à la microseconde
            // (au lieu du défaut `second`) : indispensable pour départager
            // plusieurs lignes partageant la même seconde (cf. `lot()`),
            // sans quoi la pagination peut boucler indéfiniment sur un
            // import en masse qui dépasse `LOT_MAX` lignes dans la même
            // seconde.
            'curseur' => $curseurRendu->utc()->toIso8601ZuluString('microsecond'),
            // Tant que `complet` est faux, le client rappelle immédiatement
            // avec le curseur renvoyé au lieu d'attendre le prochain cycle.
            'complet' => ! $tronque,
            // Toujours un objet, jamais un tableau : un `[]` PHP vide se
            // sérialise en `[]` JSON, ce qui casserait le typage côté Dart
            // dès qu'un delta ne rapporte rien — le cas le plus fréquent.
            'donnees' => (object) $donnees,
            'suppressions' => $this->suppressions($schoolId, $depuis, $entitesDemandees),
        ]);
    }

    /**
     * Pousse un lot d'opérations de l'outbox mobile.
     *
     * Chaque opération est un appel interne à la route métier correspondante :
     * le contrôleur, ses règles de validation et ses privilèges s'appliquent
     * donc à l'identique. Dupliquer ici la logique d'écriture de 84 endpoints
     * serait la garantie d'une divergence silencieuse entre ce que le web
     * autorise et ce que le mobile écrit.
     *
     * Chaque opération réussit ou échoue indépendamment : un refus de
     * validation sur la troisième ne doit pas annuler les deux premières, que
     * le client considérerait alors à tort comme envoyées.
     */
    public function push(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'operations' => ['required', 'array', 'min:1', 'max:'.self::LOT_PUSH_MAX],
            'operations.*.id' => ['required', 'string', 'max:100'],
            'operations.*.methode' => ['required', Rule::in(['POST', 'PUT', 'PATCH', 'DELETE'])],
            'operations.*.chemin' => ['required', 'string', 'max:255'],
            'operations.*.school_id' => ['nullable', 'integer'],
            'operations.*.corps' => ['nullable', 'array'],
        ]);

        $resultats = [];

        foreach ($valide['operations'] as $operation) {
            $resultats[] = $this->rejouer($request, $operation);
        }

        return ApiResponse::success(['resultats' => $resultats]);
    }

    /**
     * @param  array{id: string, methode: string, chemin: string, corps: ?array}  $operation
     * @return array{id: string, statut: int, reponse: mixed}
     */
    private function rejouer(Request $request, array $operation): array
    {
        // Le chemin vient du client : on le reconstruit sous le préfixe de
        // l'API plutôt que de le prendre tel quel, sinon une opération forgée
        // pourrait viser n'importe quelle route de l'application.
        $chemin = '/api/v1/'.ltrim(str_replace('..', '', $operation['chemin']), '/');
        $corps = $operation['corps'] ?? [];
        $fichiersTemporaires = [];
        $fichiers = $this->extraireFichiers($corps, $fichiersTemporaires);

        $sousRequete = Request::create(
            $chemin,
            $operation['methode'],
            [],
            [],
            $fichiers,
            // En-têtes à propager : l'établissement DE L'OPÉRATION (pas celui,
            // sans rapport, de l'appel `/api/v1/sync` englobant), et surtout
            // la clé d'idempotence — c'est elle qui rend le rejeu d'un lot
            // inoffensif.
            $this->enTetesServeur($request, $operation['id'], $operation['school_id'] ?? null),
            json_encode($corps)
        );

        $sousRequete->setUserResolver(fn () => $request->user());

        try {
            $reponse = app()->handle($sousRequete);

            return [
                'id' => $operation['id'],
                'statut' => $reponse->getStatusCode(),
                'reponse' => json_decode($reponse->getContent(), true),
            ];
        } catch (\Throwable $e) {
            // Une opération qui casse ne doit pas emporter le lot : le client
            // la conservera dans son outbox et la signalera à l'utilisateur.
            report($e);

            return [
                'id' => $operation['id'],
                'statut' => 500,
                'reponse' => ['message' => "L'opération n'a pas pu être traitée."],
            ];
        } finally {
            foreach ($fichiersTemporaires as $cheminTemporaire) {
                @unlink($cheminTemporaire);
            }
        }
    }

    /**
     * Reconstitue en `UploadedFile` réels les fichiers que
     * `EnregistrerDansOutboxLocale::encoderFichier()` a encodés en base64
     * côté client — sans quoi le contrôleur métier rejoué ici ne les
     * retrouverait jamais via `$request->file(...)`.
     *
     * Retire chaque marqueur de `$corps` au passage : son base64 (jusqu'à
     * plusieurs Mo) n'a rien à faire dans le corps JSON une fois le vrai
     * fichier extrait à côté.
     *
     * @param  array<string, mixed>  $corps
     * @param  list<string>  $fichiersTemporaires  Rempli avec les chemins des
     *                                              fichiers temporaires créés, à
     *                                              nettoyer après le rejeu.
     * @return array<string, mixed>
     */
    private function extraireFichiers(array &$corps, array &$fichiersTemporaires): array
    {
        $fichiers = [];

        foreach ($corps as $champ => $valeur) {
            if (! is_array($valeur)) {
                continue;
            }

            if (($valeur['__sync_fichier__'] ?? false) === true) {
                $fichiers[$champ] = $this->decoderFichier($valeur, $fichiersTemporaires);
                unset($corps[$champ]);

                continue;
            }

            foreach ($valeur as $sousChamp => $sousValeur) {
                if (is_array($sousValeur) && ($sousValeur['__sync_fichier__'] ?? false) === true) {
                    $fichiers[$champ][$sousChamp] = $this->decoderFichier($sousValeur, $fichiersTemporaires);
                    unset($corps[$champ][$sousChamp]);
                }
            }
        }

        return $fichiers;
    }

    /** @param  list<string>  $fichiersTemporaires */
    private function decoderFichier(array $marqueur, array &$fichiersTemporaires): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'sync_fichier_');
        file_put_contents($chemin, base64_decode($marqueur['contenu_base64']));
        $fichiersTemporaires[] = $chemin;

        // `$test = true` : ce fichier n'est pas passé par un vrai upload
        // multipart (`is_uploaded_file()` échouerait sinon), c'est le mode
        // prévu par Laravel pour construire un `UploadedFile` programmatique.
        return new UploadedFile($chemin, $marqueur['nom'], $marqueur['mime'], null, true);
    }

    /** @return array<string, string> */
    private function enTetesServeur(Request $request, string $idOperation, ?int $schoolId): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => (string) $request->header('Authorization'),
            // L'école de L'OPÉRATION quand l'outbox locale l'a fournie (poste
            // desktop) ; à défaut (outbox mobile, plus ancienne, sans cette
            // colonne), on retombe sur le contexte de l'appel englobant —
            // correct pour un compte borné à une seule école, seul cas que
            // le mobile connaît.
            'HTTP_X_SCHOOL_ID' => (string) ($schoolId ?? app('tenant.school_id')),
            // L'identifiant d'opération de l'outbox fait office de clé
            // d'idempotence : rejouer le lot entier ne recrée rien.
            'HTTP_IDEMPOTENCY_KEY' => $idOperation,
        ];
    }

    /**
     * Un lot d'une entité, et la borne à retenir si le lot a été tronqué.
     *
     * @param  array{modele: class-string, colonnes: list<string>, portee: callable, permission: ?string}  $definition
     * @return array{0: Collection, 1: ?Carbon}
     */
    private function lot(array $definition, int $schoolId, ?Carbon $depuis): array
    {
        // `updated_at` est sélectionné même s'il n'est pas exposé : c'est lui
        // qui porte le curseur. Il est retiré de la charge utile plus loin,
        // par la projection sur `colonnes`.
        $construire = function () use ($definition, $schoolId, $depuis) {
            $requete = $definition['modele']::query()
                ->select(array_values(array_unique([...$definition['colonnes'], 'updated_at'])))
                ->orderBy('updated_at')
                ->orderBy('id');

            ($definition['portee'])($requete, $schoolId);

            if ($depuis !== null) {
                // Un `Carbon` lié tel quel est tronqué à la seconde par
                // `Connection::prepareBindings()` (format `Y-m-d H:i:s`,
                // sans fraction) AVANT même d'atteindre la base — quelle que
                // soit la précision réellement stockée en colonne. Passer une
                // chaîne déjà formatée à la microseconde contourne cette
                // troncature. Sans ce détour : dès qu'une entité compte plus
                // de `LOT_MAX` lignes partageant la même seconde (un import
                // en masse, typiquement), le curseur rendu retombe toujours
                // sur cette même seconde et la pagination boucle
                // indéfiniment sans jamais avancer — observé en conditions
                // réelles sur l'établissement le plus volumineux.
                //
                // `config('app.timezone')` n'est PAS UTC ici (Africa/Douala,
                // UTC+1) : les colonnes DATETIME stockent l'heure locale sans
                // information de fuseau. `$depuis` arrive taggué UTC (parsé
                // depuis un curseur `...Z`) — le formater tel quel produit une
                // chaîne UTC comparée naïvement à des valeurs locales, décalée
                // d'une heure entière. Toute ligne locale antérieure à
                // `$depuis` mais dont l'heure d'horloge (sans le fuseau) reste
                // numériquement supérieure au filtre repassait injustement le
                // test — observé en conditions réelles : des lignes vieilles
                // de plusieurs jours réapparaissant indéfiniment quel que soit
                // le curseur envoyé.
                $requete->where(
                    'updated_at', '>',
                    $depuis->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s.u'),
                );
            }

            return $requete;
        };

        // Une ligne de plus que le lot : si elle arrive, c'est qu'il en reste.
        $lignes = $construire()->limit(self::LOT_MAX + 1)->get();

        if ($lignes->count() <= self::LOT_MAX) {
            return [$lignes, null];
        }

        $lignes = $lignes->take(self::LOT_MAX);
        $borne = $lignes->last()->updated_at;

        /*
         * Les horodatages sont à la seconde : plusieurs lignes peuvent partager
         * celui de la borne. On tronque avant ce groupe, sinon avancer le
         * curseur en `>` sauterait définitivement celles restées hors du lot
         * (cas réel après un import Excel de plusieurs centaines d'élèves).
         */
        $sansQueue = $lignes->filter(fn ($ligne) => $ligne->updated_at->lt($borne))->values();

        if ($sansQueue->isNotEmpty()) {
            return [$sansQueue, $sansQueue->last()->updated_at];
        }

        // Tout le lot tient dans la même seconde : on renvoie le groupe entier,
        // quitte à dépasser le plafond, faute de quoi le curseur n'avancerait
        // jamais et le client boucherait indéfiniment.
        return [$construire()->where('updated_at', $borne)->get(), $borne];
    }

    /**
     * Pierres tombales à rejouer côté client.
     *
     * Lors d'une première synchronisation (`depuis` absent) il n'y a rien à
     * supprimer : le client part d'une base vide.
     *
     * @param  list<string>  $entites
     * @return list<array{entite: string, id: int}>
     */
    private function suppressions(int $schoolId, ?Carbon $depuis, array $entites): array
    {
        if ($depuis === null) {
            return [];
        }

        return SyncTombstone::query()
            ->whereIn('entite', $entites)
            // Même décalage que dans `lot()` : `supprime_le` est une colonne
            // DATETIME naïve (heure locale, `config('app.timezone')` n'étant
            // pas UTC), tandis que `$depuis` arrive taggué UTC depuis le
            // curseur client — sans reconversion, la comparaison se ferait
            // entre deux fuseaux différents.
            ->where('supprime_le', '>', $depuis->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s.u'))
            ->where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))
            ->orderBy('supprime_le')
            ->limit(self::LOT_MAX)
            ->get(['entite', 'entite_id'])
            ->map(fn (SyncTombstone $t) => ['entite' => $t->entite, 'id' => (int) $t->entite_id])
            ->all();
    }

    private function curseurDemande(Request $request): ?Carbon
    {
        $depuis = $request->query('depuis');

        if (! is_string($depuis) || $depuis === '') {
            return null;
        }

        try {
            // Filet pour un client qui n'aurait pas encodé son curseur : le
            // `+` d'un décallage horaire arrive alors sous forme d'espace.
            return Carbon::parse(preg_replace('/ (\d{2}:\d{2})$/', '+$1', $depuis));
        } catch (\Throwable) {
            // Curseur illisible : on retombe sur une synchronisation complète
            // plutôt que de renvoyer une erreur qui bloquerait le téléphone
            // dans un état dont il ne peut pas sortir seul.
            return null;
        }
    }

    /**
     * Permet au client de ne réclamer qu'une partie du catalogue — l'écran
     * d'appel n'a pas besoin du référentiel des matières.
     *
     * @return list<string>
     */
    private function entitesDemandees(Request $request): array
    {
        $demandees = $request->query('entites');

        if (! is_string($demandees) || $demandees === '') {
            return RegistreSync::cles();
        }

        $filtrees = array_values(array_filter(
            array_map('trim', explode(',', $demandees)),
            fn (string $cle) => RegistreSync::existe($cle)
        ));

        return $filtrees ?: RegistreSync::cles();
    }
}
