<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejeu sans effet des écritures portant un en-tête `Idempotency-Key`.
 *
 * L'application mobile met ses écritures en file d'attente et les rejoue au
 * retour du réseau. Une requête qui aboutit côté serveur mais dont la réponse
 * se perd en chemin serait rejouée — et créerait un doublon : deux sanctions,
 * deux versements. On mémorise donc la réponse et on la resert à l'identique.
 *
 * Sans en-tête, le middleware ne fait rien : l'application web n'en envoie pas
 * et n'en a pas besoin, ses requêtes n'étant jamais rejouées automatiquement.
 */
class Idempotence
{
    /** Fenêtre de mémorisation : bien au-delà d'une coupure réseau plausible. */
    private const RETENTION_HEURES = 48;

    public function handle(Request $request, Closure $next): Response
    {
        $cle = $request->header('Idempotency-Key');
        $user = $request->user();

        if (! is_string($cle) || $cle === '' || ! $user) {
            return $next($request);
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $empreinte = $this->empreinte($request);
        $existante = IdempotencyKey::where('user_id', $user->id)->where('cle', $cle)->first();

        if ($existante) {
            /*
             * Même clé, charge utile différente : ce n'est pas un rejeu mais un
             * bug côté client (clé réutilisée pour une autre opération). On
             * refuse plutôt que de renvoyer la réponse d'une action qui n'est
             * pas celle demandée.
             */
            if (! hash_equals($existante->empreinte, $empreinte)) {
                return ApiResponse::error(
                    "Cette clé d'idempotence a déjà servi pour une requête différente.",
                    409
                );
            }

            return $this->rejouer($existante);
        }

        /*
         * Réservation avant traitement : l'unicité (user_id, cle) en base fait
         * office de verrou. Deux rejeux simultanés — cas réel quand le réseau
         * revient pendant que l'utilisateur relance l'app à la main — ne
         * peuvent donc pas s'exécuter tous les deux.
         */
        try {
            $reservation = IdempotencyKey::create([
                'cle' => $cle,
                'user_id' => $user->id,
                'empreinte' => $empreinte,
                'statut_http' => 0,
                'reponse' => '',
                'expire_le' => now()->addHours(self::RETENTION_HEURES),
            ]);
        } catch (QueryException) {
            return ApiResponse::error(
                'Une requête identique est déjà en cours de traitement.',
                409
            );
        }

        $reponse = $next($request);

        /*
         * Seules les réussites sont mémorisées. Un échec (validation, conflit
         * métier) doit pouvoir être rejoué une fois la cause corrigée, sinon le
         * client resterait bloqué sur une erreur figée pendant 48 h.
         */
        if ($reponse->getStatusCode() >= 200 && $reponse->getStatusCode() < 300) {
            $reservation->update([
                'statut_http' => $reponse->getStatusCode(),
                'reponse' => $reponse->getContent(),
            ]);
        } else {
            $reservation->delete();
        }

        return $reponse;
    }

    private function rejouer(IdempotencyKey $memorisee): Response
    {
        // Réservation encore en cours : la requête d'origine n'a pas fini.
        if ($memorisee->statut_http === 0) {
            return ApiResponse::error('Une requête identique est déjà en cours de traitement.', 409);
        }

        return response(
            $memorisee->reponse,
            $memorisee->statut_http,
            ['Content-Type' => 'application/json', 'Idempotent-Replay' => 'true']
        );
    }

    /**
     * Empreinte de la requête : méthode, chemin et corps. L'établissement en
     * fait partie — la même clé rejouée après un changement d'école viserait
     * d'autres données et ne doit pas passer pour un rejeu.
     */
    private function empreinte(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->method(),
            $request->path(),
            (string) (app()->bound('tenant.school_id') ? app('tenant.school_id') : ''),
            $request->getContent(),
        ]));
    }
}
