<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant scope (école + niveau) for the authenticated user and
 * binds it into the container : app('tenant.school_id') (un seul id, pour le
 * code non encore migré), app('tenant.school_ids') (liste, pour le code qui
 * doit voir tout le complexe), app('tenant.is_aggregate') et
 * app('tenant.niveau_id'). Voir App\Support\Tenant pour l'API à utiliser.
 *
 * super_admin has no fixed school_id and may pass X-School-Id to focus on one
 * specific établissement. Sans X-School-Id, le super admin est en mode
 * agrégé : `school_ids` couvre tout le complexe, sans qu'il ait à choisir une
 * école pour voir ses propres données.
 */
class ScopeEtablissement
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized();
        }

        $isAggregate = false;

        if (! $user->hasRole('super_admin')) {
            if (! $user->school_id) {
                return ApiResponse::forbidden("Votre compte n'est rattaché à aucun établissement.");
            }

            $schoolId = $user->school_id;
            $schoolIds = [$schoolId];
        } else {
            // Le super admin bascule d'une école à l'autre via X-School-Id, mais
            // reste borné aux établissements de son complexe : accepter l'en-tête
            // tel quel ouvrirait l'accès à n'importe quel établissement.
            $accessibles = $user->ecolesAccessibles();

            if ($accessibles->isEmpty()) {
                return ApiResponse::forbidden("Aucun établissement actif n'est configuré.");
            }

            $demande = (int) $request->header('X-School-Id');

            if ($demande && ! $accessibles->contains('id', $demande)) {
                return ApiResponse::forbidden("Cet établissement n'est pas accessible à votre compte.");
            }

            if ($demande) {
                // Mode "focus" : le super admin consulte volontairement un seul
                // établissement (ex. réglages propres à une école).
                $schoolId = $demande;
                $schoolIds = [$demande];
            } else {
                // Pas d'en-tête : mode agrégé par défaut — tout le complexe,
                // sans que le super admin ait à choisir une école pour voir ses
                // propres données.
                $schoolIds = $accessibles->pluck('id')->all();
                $schoolId = $user->school_id ?? $accessibles->first()->id;
                $isAggregate = true;
            }
        }

        // bind() plutôt qu'instance() : $schoolId peut être null, et
        // Container::instance() rend une valeur null introuvable ensuite
        // (isset() est false sur null), ce qui fait planter
        // app('tenant.school_id') avec "Target class ... does not exist".
        app()->bind('tenant.school_id', fn () => $schoolId);
        app()->bind('tenant.school_ids', fn () => $schoolIds);
        app()->bind('tenant.is_aggregate', fn () => $isAggregate);
        app()->bind('tenant.niveau_id', fn () => $request->header('X-Niveau-Id') ?: $user->niveau_id);

        return $next($request);
    }
}
