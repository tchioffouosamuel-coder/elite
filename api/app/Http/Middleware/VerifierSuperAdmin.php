<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route au super administrateur.
 *
 * L'administration des privilèges ne peut pas se protéger elle-même par un
 * privilège : qui pourrait se l'accorder pourrait tout s'accorder. Elle
 * s'appuie donc sur le rôle, seul point fixe du système.
 */
class VerifierSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized();
        }

        if (! $user->estSuperAdmin()) {
            return ApiResponse::forbidden('Cette action est réservée au super administrateur.');
        }

        return $next($request);
    }
}
