<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant scope (école + niveau) for the authenticated user and
 * binds it into the container so repositories/services can scope their
 * queries via app('tenant.school_id') / app('tenant.niveau_id').
 *
 * super_admin has no fixed school_id and may pass X-School-Id to view a
 * specific établissement's data. Tous les services/scopes exigent un
 * school_id (int, non nullable) : sans X-School-Id, on retombe donc sur
 * l'unique établissement actif s'il n'y en a qu'un ; s'il y en a plusieurs
 * (ou aucun), on renvoie une erreur explicite plutôt qu'un 500.
 */
class ScopeEtablissement
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized();
        }

        $schoolId = $user->school_id;

        if ($user->hasRole('super_admin') && $request->header('X-School-Id')) {
            $schoolId = (int) $request->header('X-School-Id');
        }

        if (! $user->hasRole('super_admin') && ! $schoolId) {
            return ApiResponse::forbidden("Votre compte n'est rattaché à aucun établissement.");
        }

        if ($user->hasRole('super_admin') && ! $schoolId) {
            $activeSchoolIds = School::where('is_active', true)->pluck('id');

            if ($activeSchoolIds->count() === 1) {
                $schoolId = $activeSchoolIds->first();
            } else {
                return ApiResponse::forbidden(
                    $activeSchoolIds->isEmpty()
                        ? "Aucun établissement actif n'est configuré."
                        : 'Veuillez sélectionner un établissement (en-tête X-School-Id).'
                );
            }
        }

        // bind() plutôt qu'instance() : $schoolId peut être null (super_admin
        // sans X-School-Id), et Container::instance() rend une valeur null
        // introuvable ensuite (isset() est false sur null), ce qui fait
        // planter app('tenant.school_id') avec "Target class ... does not exist".
        app()->bind('tenant.school_id', fn () => $schoolId);
        app()->bind('tenant.niveau_id', fn () => $request->header('X-Niveau-Id') ?: $user->niveau_id);

        return $next($request);
    }
}
