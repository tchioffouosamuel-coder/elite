<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SyncTombstone;
use App\Support\Sync\RegistreSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        foreach ($entitesDemandees as $cle) {
            $definition = RegistreSync::entites()[$cle];

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
                $donnees[$cle] = $lignes->map->only($definition['colonnes'])->all();
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
        $tronque = $bornes !== [];
        $curseurRendu = $tronque
            ? Carbon::createFromTimestamp(min(array_map(fn (Carbon $b) => $b->getTimestamp(), $bornes)))
            : $curseur;

        return ApiResponse::success([
            // Format Zulu (`...Z`) et non `+00:00` : le `+` d'un décalage se
            // décode en espace dans une chaîne de requête, rendant le curseur
            // illisible au retour et provoquant une resynchronisation complète
            // silencieuse à chaque appel.
            'curseur' => $curseurRendu->utc()->toIso8601ZuluString(),
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

        $sousRequete = Request::create(
            $chemin,
            $operation['methode'],
            [],
            [],
            [],
            // En-têtes à propager : l'établissement courant, et surtout la clé
            // d'idempotence — c'est elle qui rend le rejeu d'un lot inoffensif.
            $this->enTetesServeur($request, $operation['id']),
            json_encode($operation['corps'] ?? [])
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
        }
    }

    /** @return array<string, string> */
    private function enTetesServeur(Request $request, string $idOperation): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => (string) $request->header('Authorization'),
            'HTTP_X_SCHOOL_ID' => (string) app('tenant.school_id'),
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
                $requete->where('updated_at', '>', $depuis);
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
            ->where('supprime_le', '>', $depuis)
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
