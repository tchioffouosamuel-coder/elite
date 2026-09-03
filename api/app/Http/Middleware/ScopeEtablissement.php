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
 * Un seul mode de résolution pour tous les comptes, basé sur
 * `User::ecolesAccessibles()` : le super admin (tout le complexe) et un
 * compte de direction transverse (« Directrice Primaire et Maternelle »,
 * chauffeur/infirmier/vendeur des deux écoles) s'y comportent pareil — X-School-Id
 * pour se concentrer sur un seul établissement, sinon mode agrégé dès qu'il y
 * en a plusieurs. Un compte mono-école garde le comportement d'avant : un
 * seul `school_id`, jamais agrégé.
 */
class ScopeEtablissement
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized();
        }

        $accessibles = $user->ecolesAccessibles();

        if ($accessibles->isEmpty()) {
            return ApiResponse::forbidden("Votre compte n'est rattaché à aucun établissement.");
        }

        $isAggregate = false;
        $demande = (int) $request->header('X-School-Id');

        if ($demande && ! $accessibles->contains('id', $demande)) {
            return ApiResponse::forbidden("Cet établissement n'est pas accessible à votre compte.");
        }

        if ($demande) {
            // Mode "focus" : le compte consulte volontairement un seul
            // établissement (ex. réglages propres à une école).
            $schoolId = $demande;
            $schoolIds = [$demande];
        } elseif ($accessibles->count() === 1) {
            // Compte mono-école (l'immense majorité) : jamais agrégé, comme
            // avant l'introduction du multi-écoles.
            $schoolId = $accessibles->first()->id;
            $schoolIds = [$schoolId];
        } else {
            // Pas d'en-tête, plusieurs écoles accessibles : mode agrégé par
            // défaut — toutes, sans que le compte ait à en choisir une pour
            // voir ses propres données. `$user->school_id` ne sert de repli
            // que s'il pointe encore vers une école accessible : sinon
            // (école désactivée ou retirée du compte), une écriture non
            // explicitement ciblée irait s'y perdre silencieusement — hors
            // de portée de toute liste qui lit `school_ids`.
            $schoolIds = $accessibles->pluck('id')->all();
            $schoolId = $user->school_id !== null && $accessibles->contains('id', $user->school_id)
                ? $user->school_id
                : $accessibles->first()->id;
            $isAggregate = true;
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
